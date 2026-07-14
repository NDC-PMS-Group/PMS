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

    public function test_spg_projects_use_separate_spg_sequence(): void
    {
        $year = date('Y');
        $this->createExistingProject("BDG-{$year}-008", 'bdg_investment');

        $jv = $this->postJson('/api/projects', $this->projectPayload('spg_jv'));
        $jv->assertCreated();
        $this->assertSame("SPG-{$year}-001", $jv->json('data.project_code'));

        $traditional = $this->postJson('/api/projects', $this->projectPayload('spg_traditional'));
        $traditional->assertCreated();
        $this->assertSame("SPG-{$year}-002", $traditional->json('data.project_code'));

        $owned = $this->postJson('/api/projects', $this->projectPayload('spg_ndc_own'));
        $owned->assertCreated();
        $this->assertSame("SPG-{$year}-003", $owned->json('data.project_code'));
    }

    public function test_project_code_prefix_follows_track_and_svf_flag(): void
    {
        $year = date('Y');

        $bdg = $this->postJson('/api/projects', $this->projectPayload('bdg_investment'));
        $bdg->assertCreated();
        $this->assertSame("BDG-{$year}-001", $bdg->json('data.project_code'));

        $this->postJson('/api/projects', $this->projectPayload('implementation_monitoring'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['process_track', 'origin_track']);

        $this->postJson('/api/projects', $this->projectPayload('divestment'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['process_track', 'origin_track']);

        $svf = $this->postJson('/api/projects', $this->projectPayload('bdg_investment', ['is_svf' => true]));
        $svf->assertCreated();
        $this->assertSame("SVF-{$year}-001", $svf->json('data.project_code'));
    }

    public function test_svf_is_selected_as_a_bdg_variant_not_a_project_type(): void
    {
        $legacySvfType = ProjectType::create([
            'name' => 'SVF Project',
            'description' => 'Legacy project type retained for existing records',
        ]);

        $this->postJson('/api/projects', $this->projectPayload('bdg_investment', [
            'project_type_id' => $legacySvfType->id,
            'is_svf' => true,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_type_id']);

        $this->postJson('/api/projects', $this->projectPayload('bdg_investment', [
            'is_svf' => true,
        ]))->assertCreated();
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

    public function test_spg_origins_create_soi_requirements_without_delivery_tasks(): void
    {
        $jv = $this->postJson('/api/projects', $this->projectPayload('spg_jv'));
        $jv->assertCreated();

        $this->assertDatabaseMissing('tasks', ['project_id' => $jv->json('data.id')]);
        $this->assertDatabaseHas('project_requirements', ['project_id' => $jv->json('data.id')]);

        $owned = $this->postJson('/api/projects', $this->projectPayload('spg_ndc_own'));
        $owned->assertCreated();

        $this->assertDatabaseMissing('tasks', ['project_id' => $owned->json('data.id')]);
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
