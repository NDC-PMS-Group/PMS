<?php

namespace Tests\Feature;

use App\Models\Industry;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\ProjectType;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectCodeGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ProjectType $projectType;
    private Industry $industry;
    private Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $role = Role::create([
            'name' => 'Project Admin',
            'description' => 'Can create projects',
            'is_system_role' => true,
        ]);

        $permission = Permission::create([
            'name' => 'projects.create',
            'resource' => 'projects',
            'action' => 'create',
            'description' => 'Create projects',
        ]);
        $role->permissions()->attach($permission->id);

        $this->user = User::create([
            'username' => 'code-admin',
            'email' => 'code-admin@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Code',
            'last_name' => 'Admin',
            'default_role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->projectType = ProjectType::create(['name' => 'Infrastructure', 'description' => 'Infra']);
        $this->industry = Industry::create(['name' => 'Energy', 'description' => 'Energy']);
        $this->sector = Sector::create(['name' => 'Public', 'description' => 'Public']);

        foreach (['Intake', 'Implementation & Monitoring', 'Divestment'] as $index => $stage) {
            ProjectStage::create([
                'name' => $stage,
                'sequence_order' => $index + 1,
                'is_active' => true,
            ]);
        }

        foreach (['Draft', 'LOI Received', 'Monitoring Ongoing', 'For Divestment'] as $status) {
            ProjectStatus::create([
                'name' => $status,
                'color_code' => '#2563eb',
                'is_active' => true,
            ]);
        }

        Sanctum::actingAs($this->user);
    }

    public function test_joint_venture_and_ndc_initiated_projects_use_the_existing_spg_sequence(): void
    {
        $year = date('Y');
        $this->createExistingProject("BDG-{$year}-008", 'bdg_investment');

        $jv = $this->postJson('/api/projects', $this->projectPayload('joint_venture'));
        $jv->assertCreated();
        $this->assertSame("SPG-{$year}-001", $jv->json('data.project_code'));

        $owned = $this->postJson('/api/projects', $this->projectPayload('ndc_initiated'));
        $owned->assertCreated();
        $this->assertSame("SPG-{$year}-002", $owned->json('data.project_code'));
    }

    public function test_project_code_prefix_follows_track_and_svf_flag(): void
    {
        $year = date('Y');

        $bdg = $this->postJson('/api/projects', $this->projectPayload('traditional_external'));
        $bdg->assertCreated();
        $this->assertSame("BDG-{$year}-001", $bdg->json('data.project_code'));

        $this->postJson('/api/projects', $this->projectPayload('implementation_monitoring'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['process_track', 'origin_track']);

        $this->postJson('/api/projects', $this->projectPayload('divestment'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['process_track', 'origin_track']);

        $svf = $this->postJson('/api/projects', $this->projectPayload('startup_venture'));
        $svf->assertCreated();
        $this->assertSame("SVF-{$year}-001", $svf->json('data.project_code'));
        $svf->assertJsonPath('data.project_category_key', 'startup_venture');
    }

    public function test_startup_venture_is_selected_as_a_category_not_a_project_type(): void
    {
        $legacySvfType = ProjectType::create([
            'name' => 'SVF Project',
            'description' => 'Legacy project type retained for existing records',
        ]);

        $this->postJson('/api/projects', $this->projectPayload('startup_venture', [
            'project_type_id' => $legacySvfType->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_type_id']);

        $this->postJson('/api/projects', $this->projectPayload('startup_venture'))
            ->assertCreated()
            ->assertJsonPath('data.project_category_label', 'Startup Venture');
    }

    public function test_migration_corrects_only_auto_generated_bdg_codes_on_spg_tracks(): void
    {
        $year = date('Y');
        $this->createExistingProject("SPG-{$year}-005", 'spg_jv');
        $badSpg = $this->createExistingProject("BDG-{$year}-008", 'spg_jv');
        $bdg = $this->createExistingProject("BDG-{$year}-009", 'bdg_investment');
        $legacy = $this->createExistingProject("NDC-JV-{$year}-002", 'spg_jv');

        $migration = require database_path('migrations/2026_06_18_000004_correct_spg_project_code_prefixes.php');
        $migration->up();

        $this->assertSame("SPG-{$year}-006", $badSpg->fresh()->project_code);
        $this->assertSame("BDG-{$year}-009", $bdg->fresh()->project_code);
        $this->assertSame("NDC-JV-{$year}-002", $legacy->fresh()->project_code);
    }

    public function test_spg_origins_create_soi_requirements_and_work_plan_tasks(): void
    {
        $jv = $this->postJson('/api/projects', $this->projectPayload('joint_venture'));
        $jv->assertCreated();

        $this->assertDatabaseHas('tasks', ['project_id' => $jv->json('data.id'), 'task_scope' => 'workflow']);
        $this->assertDatabaseHas('project_requirements', ['project_id' => $jv->json('data.id')]);

        $owned = $this->postJson('/api/projects', $this->projectPayload('ndc_initiated'));
        $owned->assertCreated();

        $this->assertDatabaseHas('tasks', ['project_id' => $owned->json('data.id'), 'task_scope' => 'workflow']);
        $this->assertDatabaseHas('project_requirements', ['project_id' => $owned->json('data.id')]);
    }

    public function test_spg_task_section_migration_retargets_existing_parent_and_subtasks(): void
    {
        $project = $this->createExistingProject('SPG-2026-MIG', 'spg_jv');

        $parentId = DB::table('tasks')->insertGetId([
            'project_id' => $project->id,
            'title' => '4. NEDA-ICC approval and JV-SC composition',
            'task_type' => 'approval',
            'soi_section' => 'management_review',
            'assigned_by' => $this->user->id,
            'status' => 'pending',
            'progress_percentage' => 0,
            'priority' => 'high',
            'is_milestone' => true,
            'is_deleted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $childId = DB::table('tasks')->insertGetId([
            'project_id' => $project->id,
            'parent_task_id' => $parentId,
            'title' => 'Prepare and submit JV proposal to NEDA-ICC',
            'task_type' => 'approval',
            'soi_section' => 'management_review',
            'assigned_by' => $this->user->id,
            'status' => 'pending',
            'progress_percentage' => 0,
            'priority' => 'high',
            'is_milestone' => false,
            'is_deleted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_06_18_000007_align_spg_task_phase_sections.php');
        $migration->up();

        $this->assertSame('board_approval', DB::table('tasks')->where('id', $parentId)->value('soi_section'));
        $this->assertSame('board_approval', DB::table('tasks')->where('id', $childId)->value('soi_section'));
    }


    public function test_uat_details_round_trip_without_changing_the_generated_code(): void
    {
        $payload = $this->projectPayload('traditional_external', [
            'record_type' => 'investment',
            'project_type_id' => ProjectType::where('name', 'Others')->value('id'),
            'project_type_other' => 'Strategic partnership',
            'industry_id' => Industry::where('name', 'Others')->value('id'),
            'industry_other' => 'Emerging industry',
            'sector_id' => Sector::where('name', 'Others')->value('id'),
            'sector_other' => 'Cross-sector',
            'estimated_cost' => 5000000,
            'ndc_participation' => 1000000,
            'operations_start_date' => '2027-01-15',
            'other_financing' => [['source' => 'Loans', 'amount' => 4000000]],
            'additional_locations' => [['region_code' => '040000000', 'region_name' => 'CALABARZON', 'province_code' => '042100000', 'province_name' => 'Cavite']],
            'narrative_content' => ['description' => ['tables' => [['caption' => 'Capacity', 'rows' => [['Year', 'Output'], ['2027', '1000']]]], 'images' => []]],
        ]);
        $created = $this->postJson('/api/projects', $payload)->assertCreated()
            ->assertJsonPath('data.project_type_other', 'Strategic partnership')
            ->assertJsonPath('data.industry_other', 'Emerging industry')
            ->assertJsonPath('data.sector_other', 'Cross-sector')
            ->assertJsonPath('data.ndc_participation', '1000000.00')
            ->assertJsonPath('data.operations_start_date', '2027-01-15')
            ->assertJsonPath('data.additional_locations.0.province_name', 'Cavite')
            ->assertJsonPath('data.narrative_content.description.tables.0.rows.1.1', '1000');
        $id = $created->json('data.id');
        $code = $created->json('data.project_code');
        $this->getJson('/api/projects/'.$id)->assertOk()
            ->assertJsonPath('data.additional_locations.0.region_name', 'CALABARZON');
        $this->putJson('/api/projects/'.$id, ['project_code' => 'CHANGED', 'additional_locations' => [], 'title' => 'Updated UAT record'])
            ->assertOk()->assertJsonPath('data.project_code', $code)->assertJsonCount(0, 'data.additional_locations');
    }

    public function test_uat_other_classifications_require_explanations_and_reject_invalid_content(): void
    {
        $this->postJson('/api/projects', $this->projectPayload('traditional_external', [
            'project_type_id' => ProjectType::where('name', 'Others')->value('id'),
            'industry_id' => Industry::where('name', 'Others')->value('id'),
            'sector_id' => Sector::where('name', 'Others')->value('id'),
            'record_type' => 'portfolio',
            'operations_start_date' => 'not-a-date',
            'other_financing' => [['source' => 'Loan', 'amount' => -1]],
            'additional_locations' => [['province_name' => 'Cavite']],
            'narrative_content' => ['description' => ['images' => [['caption' => 'Unsafe', 'data' => 'data:image/svg+xml;base64,PHN2Zz4=']]]],
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'project_type_other', 'industry_other', 'sector_other', 'record_type', 'operations_start_date',
            'other_financing.0.amount', 'additional_locations.0.region_code', 'narrative_content.description.images.0.data',
        ]);
    }

    public function test_uat_rejects_changes_from_an_unrelated_user(): void
    {
        $id = $this->postJson('/api/projects', $this->projectPayload('traditional_external'))->assertCreated()->json('data.id');
        $role = Role::create(['name' => 'UAT Viewer', 'is_system_role' => false]);
        $user = User::create(['username' => 'uat-unrelated', 'email' => 'uat-unrelated@example.test', 'password_hash' => Hash::make('password'), 'first_name' => 'UAT', 'last_name' => 'Viewer', 'default_role_id' => $role->id, 'is_active' => true]);
        Sanctum::actingAs($user);
        $this->putJson('/api/projects/'.$id, ['record_type' => 'investment'])->assertForbidden();
        $this->assertNull(Project::findOrFail($id)->record_type);
    }

    public function test_uat_non_string_image_payload_returns_validation_error(): void
    {
        $this->postJson('/api/projects', $this->projectPayload('traditional_external', [
            'narrative_content' => ['description' => ['images' => [['caption' => 'Invalid image', 'data' => ['not an image']]]]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('narrative_content.description.images.0.data');
    }

    public function test_uat_map_filters_include_additional_regions_and_classification(): void
    {
        $investment = $this->createExistingProject('UAT-MAP-INV', 'traditional_external');
        $investment->update(['record_type' => 'investment', 'location_lat' => 14.5, 'location_lng' => 121.0]);
        $investment->additionalLocations()->create(['region_code' => 'R2', 'region_name' => 'Additional Region', 'province_code' => 'P2', 'province_name' => 'Additional Province']);
        $project = $this->createExistingProject('UAT-MAP-PROJ', 'traditional_external');
        $project->update(['record_type' => 'project', 'location_lat' => 14.5, 'location_lng' => 121.0]);
        $this->getJson('/api/projects/map?record_type=investment&investment_status=under_evaluation&region_code=R2&province_code=P2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $investment->id)
            ->assertJsonPath('data.0.record_type_label', 'Investment');
        $this->getJson('/api/projects/map?record_type=project&region_code=R2')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/reports/projects?record_type=investment&investment_status=under_evaluation')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $investment->id);
        $this->getJson('/api/reports/projects?investment_status=portfolio')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_uat_retires_only_sample_placeholder_and_preserves_existing_selection(): void
    {
        DB::table('investment_criteria')->insert([
            ['key' => 'sample', 'name' => 'SAMPLE', 'description' => 'sample', 'is_active' => true],
            ['key' => 'sampling', 'name' => 'Sampling readiness', 'description' => 'Meaningful criterion', 'is_active' => true],
        ]);
        $project = $this->createExistingProject('UAT-SAMPLE', 'traditional_external');
        $project->update(['ndc_investment_criteria' => ['sample'], 'proposal_date' => now()->toDateString()]);
        $project->members()->create(['user_id' => $this->user->id, 'role_id' => $this->user->default_role_id, 'can_view' => true, 'can_edit' => true, 'assigned_by' => $this->user->id]);
        $migration = require database_path('migrations/2026_09_24_000002_deactivate_sample_investment_criterion.php');
        $migration->up();
        $this->assertDatabaseHas('investment_criteria', ['key' => 'sample', 'is_active' => false]);
        $this->assertDatabaseHas('investment_criteria', ['key' => 'sampling', 'is_active' => true]);
        $this->putJson('/api/projects/'.$project->id, ['title' => 'Preserved selection', 'ndc_investment_criteria' => ['sample']])
            ->assertOk()->assertJsonPath('data.ndc_investment_criteria.0', 'sample');
        $this->postJson('/api/projects', $this->projectPayload('traditional_external', ['ndc_investment_criteria' => ['sample']]))
            ->assertUnprocessable()->assertJsonValidationErrors('ndc_investment_criteria.0');
    }

    public function test_uat_classification_cannot_change_after_a_release_record_exists(): void
    {
        $project = $this->createExistingProject('UAT-LOCK', 'traditional_external');
        $project->update(['record_type' => 'investment']);
        $project->fundReleases()->create(['status' => 'draft', 'amount' => 100]);
        $this->putJson('/api/projects/'.$project->id, ['record_type' => 'project'])
            ->assertUnprocessable()->assertJsonValidationErrors('record_type');
        $this->assertSame('investment', $project->fresh()->record_type);
    }

    private function projectPayload(string $track, array $overrides = []): array
    {
        $stageName = match ($track) {
            'implementation_monitoring' => 'Implementation & Monitoring',
            'divestment' => 'Divestment',
            default => 'Intake',
        };

        $statusName = match ($track) {
            'implementation_monitoring' => 'Monitoring Ongoing',
            'divestment' => 'For Divestment',
            default => 'Draft',
        };

        return array_merge([
            'title' => 'Generated code test ' . $track,
            'description' => 'Project code generation test',
            'process_track' => $track,
            'project_type_id' => $this->projectType->id,
            'industry_id' => $this->industry->id,
            'sector_id' => $this->sector->id,
            'currency' => 'PHP',
            'current_stage_id' => ProjectStage::where('name', $stageName)->value('id'),
            'status_id' => ProjectStatus::where('name', $statusName)->value('id'),
            'proposal_date' => now()->toDateString(),
            'ndc_investment_criteria' => ['developmental', 'sustainable', 'inclusive'],
            'estimated_cost' => 1000000,
            'proponent_name' => 'Sample Proponent',
            'proponent_email' => 'alvindalejoyosa30@gmail.com',
            'is_svf' => false,
        ], $overrides);
    }

    private function createExistingProject(string $code, string $track): Project
    {
        return Project::create([
            'project_code' => $code,
            'title' => 'Existing ' . $code,
            'description' => 'Existing project',
            'process_track' => $track,
            'project_type_id' => $this->projectType->id,
            'industry_id' => $this->industry->id,
            'sector_id' => $this->sector->id,
            'currency' => 'PHP',
            'current_stage_id' => ProjectStage::where('name', 'Intake')->value('id'),
            'status_id' => ProjectStatus::where('name', 'Draft')->value('id'),
            'is_svf' => false,
            'is_archived' => false,
            'is_deleted' => false,
            'created_by' => $this->user->id,
        ]);
    }
}
