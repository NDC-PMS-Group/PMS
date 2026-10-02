<?php

namespace Tests\Feature;

use App\Models\Industry;
use App\Models\NotificationEventSetting;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\ProjectType;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SoiWorkflowGovernanceApiTest extends TestCase
{
    use RefreshDatabase;

    private Role $superAdminRole;
    private Role $proponentRole;
    private Role $projectOfficerRole;
    private User $superAdmin;
    private User $proponent;
    private User $otherProponent;
    private ProjectStage $intakeStage;
    private ProjectStage $implementationStage;
    private ProjectStatus $draftStatus;
    private ProjectStatus $submittedStatus;
    private ProjectStatus $monitoringStatus;
    private ProjectType $projectType;
    private Industry $industry;
    private Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::create([
            'name' => 'superadmin',
            'description' => 'Full system administrator',
            'is_system_role' => true,
        ]);
        $this->proponentRole = Role::create([
            'name' => 'Proponent',
            'description' => 'External proponent',
            'is_system_role' => true,
        ]);
        $this->projectOfficerRole = Role::create([
            'name' => 'Project Officer',
            'description' => 'NDC project officer',
            'is_system_role' => true,
        ]);

        $documentUpdate = Permission::create([
            'name' => 'documents.update',
            'resource' => 'documents',
            'action' => 'update',
            'description' => 'Review project requirements',
        ]);
        $this->projectOfficerRole->permissions()->attach($documentUpdate->id);

        $this->superAdmin = $this->createUser('superadmin', 'admin@example.com', $this->superAdminRole);
        $this->proponent = $this->createUser('proponent-one', 'proponent-one@example.com', $this->proponentRole);
        $this->otherProponent = $this->createUser('proponent-two', 'proponent-two@example.com', $this->proponentRole);

        $this->intakeStage = ProjectStage::create([
            'name' => 'Intake',
            'sequence_order' => 1,
            'description' => 'Proposal intake',
            'is_active' => true,
        ]);
        $this->implementationStage = ProjectStage::create([
            'name' => 'Implementation & Monitoring',
            'sequence_order' => 6,
            'description' => 'Implementation and monitoring',
            'is_active' => true,
        ]);
        $this->draftStatus = ProjectStatus::create([
            'name' => 'Draft',
            'color_code' => '#64748B',
            'is_active' => true,
        ]);
        $this->submittedStatus = ProjectStatus::create([
            'name' => 'Submitted',
            'color_code' => '#2563EB',
            'is_active' => true,
        ]);
        $this->monitoringStatus = ProjectStatus::create([
            'name' => 'Monitoring Ongoing',
            'color_code' => '#0EA5E9',
            'is_active' => true,
        ]);
        $this->projectType = ProjectType::create(['name' => 'Infrastructure', 'description' => 'Infrastructure']);
        $this->industry = Industry::create(['name' => 'Technology', 'description' => 'Technology']);
        $this->sector = Sector::create(['name' => 'Private', 'description' => 'Private']);
    }

    public function test_proponent_only_sees_projects_they_created_or_joined(): void
    {
        $owned = $this->createProject('BDG-2026-101', $this->proponent, [
            'status_id' => $this->submittedStatus->id,
        ]);
        $other = $this->createProject('BDG-2026-102', $this->otherProponent, [
            'status_id' => $this->submittedStatus->id,
        ]);

        Sanctum::actingAs($this->proponent);

        $response = $this->getJson('/api/projects?per_page=50');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('project_code');
        $this->assertTrue($codes->contains($owned->project_code));
        $this->assertFalse($codes->contains($other->project_code));
        $this->getJson("/api/projects/{$other->id}")
            ->assertForbidden();

        Sanctum::actingAs($this->superAdmin);
        $adminCodes = collect($this->getJson('/api/projects?per_page=50')->assertOk()->json('data'))
            ->pluck('project_code');
        $this->assertTrue($adminCodes->contains($owned->project_code));
        $this->assertTrue($adminCodes->contains($other->project_code));
    }

    public function test_draft_is_visible_only_to_its_creator_even_when_viewer_is_admin(): void
    {
        $draft = $this->createProject('BDG-2026-103', $this->proponent);

        Sanctum::actingAs($this->proponent);
        $creatorCodes = collect($this->getJson('/api/projects?per_page=50')->assertOk()->json('data'))
            ->pluck('project_code');
        $this->assertTrue($creatorCodes->contains($draft->project_code));
        $this->getJson("/api/projects/{$draft->id}")->assertOk();

        Sanctum::actingAs($this->superAdmin);
        $adminCodes = collect($this->getJson('/api/projects?per_page=50')->assertOk()->json('data'))
            ->pluck('project_code');
        $this->assertFalse($adminCodes->contains($draft->project_code));
        $this->getJson("/api/projects/{$draft->id}")->assertForbidden();
    }

    public function test_monitoring_is_ndc_activated_and_proponent_access_is_gated(): void
    {
        Mail::fake();

        $project = $this->createProject('BDG-2026-201', $this->proponent, [
            'project_officer_id' => $this->superAdmin->id,
            'status_id' => $this->submittedStatus->id,
        ]);

        $activation = [
            'reporting_year' => 2026,
            'quarter' => 3,
            'compliance_types' => ['employment'],
            'due_date' => now()->addMonth()->toDateString(),
            'instructions' => 'Submit verified employment results.',
            'proponent_access' => true,
        ];

        Sanctum::actingAs($this->proponent);
        $this->postJson("/api/projects/{$project->id}/monitoring/activate", $activation)->assertForbidden();

        Sanctum::actingAs($this->superAdmin);
        $this->postJson("/api/projects/{$project->id}/monitoring/activate", $activation)->assertUnprocessable()
            ->assertJsonPath('message', 'Monitoring can only be opened after project approval or when the project has entered implementation.');

        $project->update([
            'current_stage_id' => $this->implementationStage->id,
            'lifecycle_phase' => 'implementation_monitoring',
        ]);

        $this->postJson("/api/projects/{$project->id}/monitoring/activate", $activation)->assertOk()
            ->assertJsonPath('project.monitoring_status', 'active')
            ->assertJsonPath('project.monitoring_submission_status', 'open')
            ->assertJsonPath('project.active_monitoring_cycle.quarter', 3);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'monitoring_status' => 'active',
            'monitoring_proponent_access' => true,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->proponent->id,
            'type' => 'monitoring_activated',
        ]);
        $this->assertSame(
            1,
            $this->proponent->notifications()->where('type', 'monitoring_activated')->count(),
            'The proponent should receive one monitoring activation notice.'
        );

        $this->postJson("/api/projects/{$project->id}/monitoring/activate", $activation)->assertUnprocessable()
            ->assertJsonPath('message', 'The monitoring period is already active. Close it before opening a new period.');

        Sanctum::actingAs($this->proponent);
        $this->getJson('/api/monitoring-reports?view=grouped&scope=active')
            ->assertOk()
            ->assertJsonPath('data.0.project.id', $project->id)
            ->assertJsonPath('data.0.reports.employment', null)
            ->assertJsonPath('summary.missing', 1);

        $submission = [
            'compliance_type' => 'employment',
            'reporting_year' => 2026,
            'quarter' => 3,
            'jobs_generated_male' => 12,
            'jobs_generated_female' => 8,
            'jobs_retained_male' => 7,
            'jobs_retained_female' => 5,
        ];
        $created = $this->postJson("/api/projects/{$project->id}/monitoring-reports", $submission)
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted');
        $reportId = $created->json('data.id');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->superAdmin->id,
            'type' => 'monitoring_submitted',
        ]);
        $this->putJson("/api/projects/{$project->id}/monitoring", ['financial_metrics' => []])->assertStatus(410);

        Sanctum::actingAs($this->superAdmin);
        $this->postJson("/api/monitoring-reports/{$reportId}/review", [
            'action' => 'returned',
            'remarks' => 'Attach the verified employment schedule.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'returned');

        Sanctum::actingAs($this->proponent);
        $submission['jobs_generated_male'] = 14;
        $this->postJson("/api/monitoring-reports/{$reportId}/submit", $submission)
            ->assertOk()->assertJsonPath('data.status', 'submitted');

        Sanctum::actingAs($this->superAdmin);
        $this->postJson("/api/monitoring-reports/{$reportId}/review", [
            'action' => 'accepted',
        ])->assertOk()
            ->assertJsonPath('data.status', 'accepted');
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'monitoring_submission_status' => 'accepted']);
        $this->postJson("/api/projects/{$project->id}/monitoring/close")
            ->assertOk()
            ->assertJsonPath('project.monitoring_status', 'completed');
        $this->getJson('/api/monitoring-reports?view=grouped&scope=history')
            ->assertOk()
            ->assertJsonPath('data.0.project.id', $project->id)
            ->assertJsonPath('data.0.status', 'closed');

        Sanctum::actingAs($this->proponent);
        $this->postJson("/api/projects/{$project->id}/monitoring-reports", $submission)->assertUnprocessable();
    }

    public function test_monitoring_summary_counts_each_missing_requested_report_type(): void
    {
        Mail::fake();

        $project = $this->createProject('BDG-2026-202', $this->proponent, [
            'project_officer_id' => $this->superAdmin->id,
            'status_id' => $this->submittedStatus->id,
            'current_stage_id' => $this->implementationStage->id,
            'lifecycle_phase' => 'implementation_monitoring',
        ]);

        Sanctum::actingAs($this->superAdmin);
        $this->postJson("/api/projects/{$project->id}/monitoring/activate", [
            'reporting_year' => 2026,
            'quarter' => 3,
            'compliance_types' => ['employment', 'financial', 'progress'],
            'due_date' => now()->addMonth()->toDateString(),
            'instructions' => 'Submit all requested compliance reports.',
            'proponent_access' => true,
        ])->assertOk();

        $this->getJson('/api/monitoring-reports?view=grouped&scope=active')
            ->assertOk()
            ->assertJsonPath('summary.missing', 3);

        Sanctum::actingAs($this->proponent);
        $this->postJson("/api/projects/{$project->id}/monitoring-reports", [
            'compliance_type' => 'employment',
            'reporting_year' => 2026,
            'quarter' => 3,
            'jobs_generated_male' => 4,
            'jobs_generated_female' => 3,
            'jobs_retained_male' => 2,
            'jobs_retained_female' => 1,
        ])->assertCreated();

        $this->getJson('/api/monitoring-reports?view=grouped&scope=active')
            ->assertOk()
            ->assertJsonPath('summary.missing', 2);
    }

    public function test_proponent_can_upload_only_requested_requirements_directly(): void
    {
        Mail::fake();
        Storage::fake('public');

        $project = $this->createProject('BDG-2026-301', $this->proponent);
        $requirement = ProjectRequirement::create([
            'project_id' => $project->id,
            'group_name' => 'Initial proposal',
            'item_name' => 'Letter of Intent',
            'source_document' => 'BDG SOI',
            'track' => 'bdg_investment',
            'status' => 'pending',
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($this->proponent);
        $payload = [
            'project_id' => $project->id,
            'requirement_id' => $requirement->id,
            'title' => 'Letter of Intent',
            'file' => UploadedFile::fake()->create('loi.pdf', 120, 'application/pdf'),
        ];

        $this->postJson('/api/documents', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'NDC has not requested this requirement yet. Please attach files only to requested requirements.');

        $requirement->update([
            'status' => 'requested',
            'due_date' => now()->addWeeks(2)->toDateString(),
        ]);
        $payload['file'] = UploadedFile::fake()->create('loi.pdf', 120, 'application/pdf');

        $this->postJson('/api/documents', $payload)->assertSuccessful();

        $requirement->refresh();
        $this->assertNotNull($requirement->document_id);
        $this->assertDatabaseHas('documents', [
            'id' => $requirement->document_id,
            'project_id' => $project->id,
            'submission_status' => 'draft',
        ]);
    }

    public function test_only_administrators_can_manage_notification_rules(): void
    {
        $event = NotificationEventSetting::create([
            'event_key' => 'proposal_submitted',
            'label' => 'Proposal submitted',
            'category' => 'Project development',
            'description' => 'Notify reviewers.',
            'in_app_enabled' => true,
            'email_enabled' => true,
        ]);

        Sanctum::actingAs($this->proponent);
        $this->getJson('/api/notification-event-settings')->assertForbidden();
        $this->putJson("/api/notification-event-settings/{$event->id}", [
            'in_app_enabled' => true,
            'email_enabled' => false,
            'template_name' => null,
        ])->assertForbidden();

        Sanctum::actingAs($this->superAdmin);
        $this->getJson('/api/notification-event-settings')
            ->assertOk()
            ->assertJsonPath('events.Project development.0.event_key', 'proposal_submitted');

        $this->putJson("/api/notification-event-settings/{$event->id}", [
            'in_app_enabled' => true,
            'email_enabled' => false,
            'template_name' => null,
        ])->assertOk();

        $this->assertDatabaseHas('notification_event_settings', [
            'id' => $event->id,
            'in_app_enabled' => true,
            'email_enabled' => false,
            'updated_by' => $this->superAdmin->id,
        ]);
    }

    private function createUser(string $username, string $email, Role $role): User
    {
        return User::create([
            'username' => $username,
            'email' => $email,
            'password_hash' => Hash::make('Password123!'),
            'first_name' => ucfirst(str_replace('-', ' ', $username)),
            'last_name' => 'User',
            'default_role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function createProject(string $code, User $creator, array $overrides = []): Project
    {
        return Project::create(array_merge([
            'project_code' => $code,
            'title' => "Project {$code}",
            'description' => 'SOI workflow governance test project.',
            'process_track' => 'bdg_investment',
            'project_type_id' => $this->projectType->id,
            'industry_id' => $this->industry->id,
            'sector_id' => $this->sector->id,
            'currency' => 'PHP',
            'current_stage_id' => $this->intakeStage->id,
            'status_id' => $this->draftStatus->id,
            'proposal_date' => now()->toDateString(),
            'proponent_name' => $creator->full_name,
            'proponent_email' => $creator->email,
            'monitoring_status' => 'closed',
            'is_svf' => false,
            'is_archived' => false,
            'is_deleted' => false,
            'created_by' => $creator->id,
        ], $overrides));
    }
}
