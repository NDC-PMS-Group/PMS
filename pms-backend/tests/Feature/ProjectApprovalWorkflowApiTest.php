<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Industry;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectRequirement;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\ProjectType;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Api\ApprovalController;
use Database\Seeders\ApprovalWorkflowSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectApprovalWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_pending_approval_for_current_role(): void
    {
        $projectOfficerRole = Role::create([
            'name' => 'Project Officer',
            'description' => 'Project Officer',
            'is_system_role' => true,
        ]);

        $proponentRole = Role::create([
            'name' => 'Proponent',
            'description' => 'Proponent',
            'is_system_role' => true,
        ]);

        Permission::create([
            'name' => 'projects.create',
            'resource' => 'projects',
            'action' => 'create',
            'description' => 'Create projects',
        ]);

        $workflow = ApprovalWorkflow::create([
            'name' => 'SOI Sequential Approval',
            'description' => 'SOI flow',
            'project_type_id' => null,
            'is_active' => true,
        ]);

        $stepOne = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'role_id' => $proponentRole->id,
            'step_name' => 'Proponent Submission',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $stepTwo = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'role_id' => $projectOfficerRole->id,
            'step_name' => 'Project Officer Evaluation',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $stage = ProjectStage::create([
            'name' => 'Intake',
            'sequence_order' => 1,
            'description' => 'Intake stage',
            'is_active' => true,
        ]);

        $status = ProjectStatus::create([
            'name' => 'LOI Received',
            'color_code' => '#FFFFFF',
            'is_active' => true,
        ]);

        $type = ProjectType::create(['name' => 'Infrastructure', 'description' => 'Infra']);
        $industry = Industry::create(['name' => 'Energy', 'description' => 'Energy']);
        $sector = Sector::create(['name' => 'Public', 'description' => 'Public']);

        $user = User::create([
            'username' => 'projectofficer',
            'email' => 'projectofficer@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Project',
            'last_name' => 'Officer',
            'default_role_id' => $projectOfficerRole->id,
            'is_active' => true,
        ]);

        $project = Project::create([
            'project_code' => 'BDG-2026-001',
            'title' => 'QA Integration Project',
            'description' => 'Project created by integration test',
            'project_type_id' => $type->id,
            'industry_id' => $industry->id,
            'sector_id' => $sector->id,
            'currency' => 'PHP',
            'current_stage_id' => $stage->id,
            'status_id' => $status->id,
            'proposal_date' => '2026-02-22',
            'is_svf' => false,
            'is_archived' => false,
            'is_deleted' => false,
            'created_by' => $user->id,
        ]);

        $approval = ApprovalController::createInitialApprovalForProject(
            (int) $project->id,
            (int) $type->id,
            (int) $user->id
        );

        $this->assertNotNull($approval);

        Sanctum::actingAs($user);

        $this->assertDatabaseHas('project_approvals', [
            'project_id' => $project->id,
            'workflow_id' => $workflow->id,
            'current_step_id' => $stepTwo->id,
            'overall_status' => 'for_evaluation',
        ]);

        $pendingResponse = $this->getJson('/api/approvals/pending');
        $pendingResponse->assertOk();

        $pendingProjectIds = collect($pendingResponse->json('data', []))
            ->pluck('project_id')
            ->all();

        $this->assertContains($project->id, $pendingProjectIds);
    }

    public function test_proposal_approval_starts_when_draft_package_is_submitted(): void
    {
        Mail::fake();
        Storage::fake('public');

        $proponentRole = Role::create([
            'name' => 'Proponent',
            'description' => 'External proponent',
            'is_system_role' => true,
        ]);

        $projectOfficerRole = Role::create([
            'name' => 'Project Officer',
            'description' => 'Project Officer',
            'is_system_role' => true,
        ]);

        $createPermission = Permission::create([
            'name' => 'projects.create',
            'resource' => 'projects',
            'action' => 'create',
            'description' => 'Create projects',
        ]);
        $documentUpdatePermission = Permission::create([
            'name' => 'documents.update',
            'resource' => 'documents',
            'action' => 'update',
            'description' => 'Update project requirements',
        ]);
        $taskCreatePermission = Permission::create([
            'name' => 'tasks.create',
            'resource' => 'tasks',
            'action' => 'create',
            'description' => 'Create project work-plan tasks',
        ]);

        $proponentRole->permissions()->attach($createPermission->id);
        $proponentRole->permissions()->attach($taskCreatePermission->id);
        $projectOfficerRole->permissions()->attach($documentUpdatePermission->id);

        $workflow = ApprovalWorkflow::create([
            'name' => 'NDC BDG Investment Approval',
            'description' => 'BDG SOI flow',
            'project_type_id' => null,
            'is_active' => true,
        ]);

        $submitterStep = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'role_id' => $proponentRole->id,
            'step_name' => 'Proponent Submission',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $projectOfficerStep = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'role_id' => $projectOfficerRole->id,
            'step_name' => 'Pre-screening / KYC and LOI Receipt',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $intakeStage = ProjectStage::create([
            'name' => 'Intake',
            'sequence_order' => 1,
            'description' => 'Intake stage',
            'is_active' => true,
        ]);

        ProjectStatus::create(['name' => 'Draft', 'color_code' => '#94A3B8', 'is_active' => true]);
        ProjectStatus::create(['name' => 'LOI Received', 'color_code' => '#6366F1', 'is_active' => true]);

        $type = ProjectType::create(['name' => 'Business Development', 'description' => 'BDG']);
        $industry = Industry::create(['name' => 'Technology', 'description' => 'Technology']);
        $sector = Sector::create(['name' => 'Private', 'description' => 'Private']);

        $proponent = User::create([
            'username' => 'proponent',
            'email' => 'proponent@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'External',
            'last_name' => 'Proponent',
            'default_role_id' => $proponentRole->id,
            'is_active' => true,
        ]);

        $projectOfficer = User::create([
            'username' => 'projectofficer2',
            'email' => 'projectofficer2@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Project',
            'last_name' => 'Officer',
            'default_role_id' => $projectOfficerRole->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($proponent);

        $createResponse = $this->postJson('/api/projects', [
            'title' => 'Draft BDG Proposal',
            'description' => 'Proposal package should not route until files are submitted.',
            'process_track' => 'bdg_investment',
            'project_type_id' => $type->id,
            'industry_id' => $industry->id,
            'sector_id' => $sector->id,
            'current_stage_id' => $intakeStage->id,
            'status_id' => ProjectStatus::where('name', 'Draft')->value('id'),
            'proposal_date' => '2026-06-08',
            'currency' => 'PHP',
            'ndc_investment_criteria' => ['developmental', 'sustainable', 'inclusive'],
        ]);

        $createResponse->assertCreated();
        $projectId = $createResponse->json('data.id');

        $this->assertDatabaseHas('project_requirements', [
            'project_id' => $projectId,
            'item_name' => 'ManCom decision paper, recommendation, or presentation material',
            'owner_type' => 'internal',
            'visibility' => 'internal_only',
            'soi_section' => 'management_review',
            'gate_step' => 'mancom',
            'is_required' => true,
        ]);

        $proponentProjectResponse = $this->getJson("/api/projects/{$projectId}");
        $proponentProjectResponse->assertOk();
        $this->assertNotContains(
            'ManCom decision paper, recommendation, or presentation material',
            collect($proponentProjectResponse->json('data.requirements', []))->pluck('item_name')->all()
        );

        $this->assertDatabaseHas('tasks', [
            'project_id' => $projectId,
            'soi_section' => 'intake',
        ]);
        $this->assertDatabaseHas('tasks', [
            'project_id' => $projectId,
            'soi_section' => 'requirements',
        ]);

        $this->postJson('/api/tasks', [
            'project_id' => $projectId,
            'title' => 'Invalid SOI section task',
            'task_type' => 'requirements',
            'soi_section' => 'not_a_section',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('soi_section');

        $manualTaskResponse = $this->postJson('/api/tasks', [
            'project_id' => $projectId,
            'title' => 'Manual requirements follow-up',
            'task_type' => 'requirements',
        ]);

        $manualTaskResponse
            ->assertCreated()
            ->assertJsonPath('data.soi_section', 'requirements');

        $this->assertDatabaseHas('tasks', [
            'id' => $manualTaskResponse->json('data.id'),
            'soi_section' => 'requirements',
        ]);

        $manualTaskId = $manualTaskResponse->json('data.id');

        $this->putJson("/api/tasks/{$manualTaskId}", [
            'status' => 'completed',
            'progress_percentage' => 100,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertNotNull(Task::find($manualTaskId)?->completion_date);

        $this->putJson("/api/tasks/{$manualTaskId}", [
            'status' => 'in_progress',
            'progress_percentage' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.completion_date', null);

        $this->assertNull(Task::find($manualTaskId)?->completion_date);

        $this->assertDatabaseMissing('project_approvals', [
            'project_id' => $projectId,
        ]);
        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'status_id' => ProjectStatus::where('name', 'Draft')->value('id'),
        ]);

        $pendingRequirement = ProjectRequirement::where('project_id', $projectId)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($pendingRequirement);

        $this->postJson('/api/documents', [
            'project_id' => $projectId,
            'requirement_id' => $pendingRequirement->id,
            'title' => $pendingRequirement->item_name,
            'category' => $pendingRequirement->group_name,
            'file' => UploadedFile::fake()->create('not-yet-requested.pdf', 128, 'application/pdf'),
        ])->assertStatus(422);

        $initialRequirements = ProjectRequirement::where('project_id', $projectId)
            ->where('group_name', '1. Intake Pack')
            ->where('is_required', true)
            ->where('status', 'requested')
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(2, $initialRequirements);
        $this->assertSame(
            3,
            ProjectRequirement::where('project_id', $projectId)
                ->where('group_name', '1. Intake Pack')
                ->where('is_required', false)
                ->where('status', 'requested')
                ->count(),
            'Preliminary NDA/privacy, authority, and website files should be open without blocking initial submission.'
        );

        $this->postJson("/api/projects/{$projectId}/submit-proposal")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Complete the initial SOI proposal package before submitting.');

        $submitWithoutFiles = $this->postJson("/api/projects/{$projectId}/documents/submit-drafts");
        $submitWithoutFiles
            ->assertStatus(422)
            ->assertJsonPath('message', 'Complete the initial SOI intake package before submitting.');

        foreach ($initialRequirements as $index => $requirement) {
            $this->postJson('/api/documents', [
                'project_id' => $projectId,
                'requirement_id' => $requirement->id,
                'title' => $requirement->item_name,
                'category' => $requirement->group_name,
                'file' => UploadedFile::fake()->create("intake-{$index}.pdf", 128, 'application/pdf'),
            ])->assertCreated();
        }

        $submitResponse = $this->postJson("/api/projects/{$projectId}/documents/submit-drafts");

        $submitResponse
            ->assertOk()
            ->assertJsonPath('submitted_count', 2)
            ->assertJsonPath('approval_started', true);

        $this->assertDatabaseHas('project_approvals', [
            'project_id' => $projectId,
            'workflow_id' => $workflow->id,
            'current_step_id' => $projectOfficerStep->id,
            'overall_status' => 'pending',
        ]);

        $this->assertDatabaseHas('approval_step_records', [
            'step_id' => $submitterStep->id,
            'approver_id' => $proponent->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'status_id' => ProjectStatus::where('name', 'LOI Received')->value('id'),
        ]);

        $laterRequirement = ProjectRequirement::where('project_id', $projectId)
            ->where('status', 'pending')
            ->where('soi_section', 'due_diligence')
            ->first();

        $this->assertNotNull($laterRequirement);

        Sanctum::actingAs($projectOfficer);

        $this->patchJson("/api/projects/{$projectId}/requirements/{$laterRequirement->id}", [
            'status' => 'requested',
            'remarks' => 'Please submit latest SEC registration and Articles of Incorporation for eligibility screening.',
            'due_date' => '2026-06-20',
        ])->assertOk();

        $this->assertDatabaseHas('project_requirements', [
            'id' => $laterRequirement->id,
            'status' => 'requested',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $proponent->id,
            'type' => 'requirement_status_change',
            'related_entity_type' => Project::class,
            'related_entity_id' => $projectId,
        ]);
    }

    public function test_mancom_approval_is_blocked_until_internal_gate_artifact_is_handled(): void
    {
        $superAdminRole = Role::create([
            'name' => 'superadmin',
            'description' => 'Super Admin',
            'is_system_role' => true,
        ]);

        $manComRole = Role::create([
            'name' => 'ManCom',
            'description' => 'Management Committee',
            'is_system_role' => true,
        ]);

        $workflow = ApprovalWorkflow::create([
            'name' => 'Internal ManCom Gate Workflow',
            'description' => 'Checks internal endorsement artifact readiness.',
            'project_type_id' => null,
            'is_active' => true,
        ]);

        $manComStep = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'role_id' => $manComRole->id,
            'step_name' => 'ManCom Decision',
            'soi_section' => 'management_review',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $stage = ProjectStage::create([
            'name' => 'Management Review',
            'sequence_order' => 4,
            'description' => 'Management review',
            'is_active' => true,
        ]);

        $status = ProjectStatus::create([
            'name' => 'For ManCom Review',
            'color_code' => '#2563EB',
            'is_active' => true,
        ]);

        $type = ProjectType::create(['name' => 'Business Development', 'description' => 'BDG']);
        $industry = Industry::create(['name' => 'Energy', 'description' => 'Energy']);
        $sector = Sector::create(['name' => 'Private', 'description' => 'Private']);

        $admin = User::create([
            'username' => 'admin-gate',
            'email' => 'admin-gate@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'default_role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        $manComUser = User::create([
            'username' => 'mancom-gate',
            'email' => 'mancom-gate@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'ManCom',
            'last_name' => 'Approver',
            'default_role_id' => $manComRole->id,
            'is_active' => true,
        ]);

        $project = Project::create([
            'project_code' => 'BDG-2026-GATE',
            'title' => 'Gate Readiness Project',
            'description' => 'Project with ManCom gate artifact.',
            'project_type_id' => $type->id,
            'industry_id' => $industry->id,
            'sector_id' => $sector->id,
            'currency' => 'PHP',
            'current_stage_id' => $stage->id,
            'status_id' => $status->id,
            'proposal_date' => '2026-06-18',
            'is_svf' => false,
            'is_archived' => false,
            'is_deleted' => false,
            'created_by' => $admin->id,
        ]);

        $requirement = ProjectRequirement::create([
            'project_id' => $project->id,
            'group_name' => '5. Internal Evaluation / Endorsement',
            'item_name' => 'ManCom decision paper, recommendation, or presentation material',
            'source_document' => 'BDG/SPG SOI',
            'track' => 'bdg_investment',
            'owner_type' => 'internal',
            'visibility' => 'internal_only',
            'soi_section' => 'management_review',
            'gate_step' => 'mancom',
            'is_required' => true,
            'is_applicable' => true,
            'svf_only' => false,
            'status' => 'pending',
            'sort_order' => 10,
        ]);

        $approval = ProjectApproval::create([
            'project_id' => $project->id,
            'workflow_id' => $workflow->id,
            'current_step_id' => $manComStep->id,
            'overall_status' => 'for_mancom_review',
            'started_at' => now(),
        ]);

        Sanctum::actingAs($manComUser);

        $this->postJson("/api/approvals/{$approval->id}/approve", [
            'status' => 'approved',
            'comments' => 'Ready for endorsement.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Upload, approve, or waive the required internal SOI artifacts before completing this endorsement step.')
            ->assertJsonFragment([
                'ManCom decision paper, recommendation, or presentation material',
            ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/projects/{$project->id}/requirements/{$requirement->id}", [
            'status' => 'waived',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('remarks');

        $this->patchJson("/api/projects/{$project->id}/requirements/{$requirement->id}", [
            'status' => 'waived',
            'remarks' => 'ManCom package handled outside PMS for this legacy item.',
        ])->assertOk();

        Sanctum::actingAs($manComUser);

        $this->postJson("/api/approvals/{$approval->id}/approve", [
            'status' => 'approved',
            'comments' => 'Waiver reviewed.',
        ])->assertOk();
    }

    public function test_first_step_can_be_approved_by_its_assigned_role_even_when_it_is_not_a_proponent_step(): void
    {
        $projectOfficerRole = Role::create([
            'name' => 'Project Officer',
            'description' => 'Project Officer',
            'is_system_role' => true,
        ]);

        $manComRole = Role::create([
            'name' => 'ManCom',
            'description' => 'Management Committee',
            'is_system_role' => true,
        ]);

        $workflow = ApprovalWorkflow::create([
            'name' => 'Internal First Step Workflow',
            'description' => 'Ensures first step can belong to an internal reviewer.',
            'project_type_id' => null,
            'is_active' => true,
        ]);

        $firstStep = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'role_id' => $projectOfficerRole->id,
            'step_name' => 'Project Officer Validation',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $secondStep = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'role_id' => $manComRole->id,
            'step_name' => 'ManCom Decision',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $stage = ProjectStage::create([
            'name' => 'Intake',
            'sequence_order' => 1,
            'description' => 'Intake stage',
            'is_active' => true,
        ]);

        $status = ProjectStatus::create([
            'name' => 'For Evaluation',
            'color_code' => '#2563EB',
            'is_active' => true,
        ]);

        $type = ProjectType::create(['name' => 'Business Development', 'description' => 'BDG']);
        $industry = Industry::create(['name' => 'Energy', 'description' => 'Energy']);
        $sector = Sector::create(['name' => 'Private', 'description' => 'Private']);

        $projectOfficer = User::create([
            'username' => 'projectofficer-step1',
            'email' => 'projectofficer-step1@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Project',
            'last_name' => 'Officer',
            'default_role_id' => $projectOfficerRole->id,
            'is_active' => true,
        ]);

        $project = Project::create([
            'project_code' => 'BDG-2026-STEP1',
            'title' => 'Step One Internal Review Project',
            'description' => 'Project with an internal first approval step.',
            'project_type_id' => $type->id,
            'industry_id' => $industry->id,
            'sector_id' => $sector->id,
            'currency' => 'PHP',
            'current_stage_id' => $stage->id,
            'status_id' => $status->id,
            'proposal_date' => '2026-06-18',
            'is_svf' => false,
            'is_archived' => false,
            'is_deleted' => false,
            'created_by' => $projectOfficer->id,
        ]);

        $approval = ProjectApproval::create([
            'project_id' => $project->id,
            'workflow_id' => $workflow->id,
            'current_step_id' => $firstStep->id,
            'overall_status' => 'pending',
            'started_at' => now(),
        ]);

        Sanctum::actingAs($projectOfficer);

        $this->postJson("/api/approvals/{$approval->id}/approve", [
            'status' => 'approved',
            'comments' => 'Validated and ready for ManCom review.',
        ])->assertOk();

        $this->assertDatabaseHas('approval_step_records', [
            'project_approval_id' => $approval->id,
            'step_id' => $firstStep->id,
            'approver_id' => $projectOfficer->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('project_approvals', [
            'id' => $approval->id,
            'current_step_id' => $secondStep->id,
        ]);
    }

    public function test_jv_conceptualization_step_does_not_get_blocked_by_internal_gate_artifacts(): void
    {
        $projectOfficerRole = Role::create([
            'name' => 'Project Officer',
            'description' => 'Project Officer',
            'is_system_role' => true,
        ]);

        $workflow = ApprovalWorkflow::create([
            'name' => 'SPG Joint Venture Project Approval',
            'description' => 'JV flow',
            'project_type_id' => null,
            'is_active' => true,
        ]);

        $conceptStep = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'role_id' => $projectOfficerRole->id,
            'step_name' => 'JV Project Conceptualization',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $manComRole = Role::create([
            'name' => 'ManCom',
            'description' => 'Management Committee',
            'is_system_role' => true,
        ]);

        $manComStep = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'role_id' => $manComRole->id,
            'step_name' => 'ManCom Decision',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $stage = ProjectStage::create([
            'name' => 'Intake',
            'sequence_order' => 1,
            'description' => 'Intake stage',
            'is_active' => true,
        ]);

        $status = ProjectStatus::create([
            'name' => 'For Evaluation',
            'color_code' => '#2563EB',
            'is_active' => true,
        ]);

        $type = ProjectType::create(['name' => 'Joint Venture', 'description' => 'JV']);
        $industry = Industry::create(['name' => 'Energy', 'description' => 'Energy']);
        $sector = Sector::create(['name' => 'Private', 'description' => 'Private']);

        $projectOfficer = User::create([
            'username' => 'projectofficer-jv',
            'email' => 'projectofficer-jv@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Project',
            'last_name' => 'Officer',
            'default_role_id' => $projectOfficerRole->id,
            'is_active' => true,
        ]);

        $project = Project::create([
            'project_code' => 'SPG-2026-JV1',
            'title' => 'JV Concept Project',
            'description' => 'Project with JV conceptualization step.',
            'project_type_id' => $type->id,
            'industry_id' => $industry->id,
            'sector_id' => $sector->id,
            'currency' => 'PHP',
            'current_stage_id' => $stage->id,
            'status_id' => $status->id,
            'proposal_date' => '2026-06-18',
            'is_svf' => false,
            'is_archived' => false,
            'is_deleted' => false,
            'created_by' => $projectOfficer->id,
        ]);

        $approval = ProjectApproval::create([
            'project_id' => $project->id,
            'workflow_id' => $workflow->id,
            'current_step_id' => $conceptStep->id,
            'overall_status' => 'pending',
            'started_at' => now(),
        ]);

        ProjectRequirement::create([
            'project_id' => $project->id,
            'group_name' => '5. Internal Evaluation / Endorsement',
            'item_name' => 'JV partner selection package',
            'source_document' => 'SOI',
            'track' => 'spg_jv',
            'owner_type' => 'internal',
            'visibility' => 'internal_only',
            'soi_section' => 'management_review',
            'gate_step' => 'jv',
            'is_required' => true,
            'is_applicable' => true,
            'svf_only' => false,
            'status' => 'pending',
            'sort_order' => 10,
        ]);

        Sanctum::actingAs($projectOfficer);

        $this->postJson("/api/approvals/{$approval->id}/approve", [
            'status' => 'approved',
            'comments' => 'Concept endorsed.',
        ])->assertOk();

        $this->assertDatabaseHas('approval_step_records', [
            'project_approval_id' => $approval->id,
            'step_id' => $conceptStep->id,
            'approver_id' => $projectOfficer->id,
            'status' => 'approved',
        ]);
    }

    public function test_spg_jv_mancom_approval_to_proceed_is_not_blocked_by_later_mancom_artifacts(): void
    {
        $projectOfficerRole = Role::create(['name' => 'Project Officer', 'description' => 'Project Officer', 'is_system_role' => true]);
        $manComRole = Role::create(['name' => 'ManCom', 'description' => 'Management Committee', 'is_system_role' => true]);

        $workflow = ApprovalWorkflow::create([
            'name' => 'SPG Joint Venture Project Approval',
            'description' => 'JV flow',
            'project_type_id' => null,
            'is_active' => true,
        ]);

        $approvalToProceed = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'role_id' => $manComRole->id,
            'step_name' => 'ManCom Approval to Proceed',
            'soi_section' => 'management_review',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $projectDecision = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 4,
            'role_id' => $manComRole->id,
            'step_name' => 'ManCom JV Project Decision',
            'soi_section' => 'management_review',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $stage = ProjectStage::create(['name' => 'Management Review', 'sequence_order' => 3, 'description' => 'Management review', 'is_active' => true]);
        $status = ProjectStatus::create(['name' => 'For ManCom Review', 'color_code' => '#2563EB', 'is_active' => true]);
        $type = ProjectType::create(['name' => 'Joint Venture', 'description' => 'JV']);
        $industry = Industry::create(['name' => 'Infrastructure', 'description' => 'Infrastructure']);
        $sector = Sector::create(['name' => 'Public', 'description' => 'Public']);

        $manComUser = User::create([
            'username' => 'mancom-proceed',
            'email' => 'mancom-proceed@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'ManCom',
            'last_name' => 'Proceed',
            'default_role_id' => $manComRole->id,
            'is_active' => true,
        ]);

        $project = Project::create([
            'project_code' => 'SPG-2026-PROCEED',
            'title' => 'SPG Approval To Proceed',
            'description' => 'Early SPG JV ManCom step.',
            'project_type_id' => $type->id,
            'industry_id' => $industry->id,
            'sector_id' => $sector->id,
            'currency' => 'PHP',
            'current_stage_id' => $stage->id,
            'status_id' => $status->id,
            'proposal_date' => '2026-06-18',
            'process_track' => 'spg_jv',
            'is_svf' => false,
            'is_archived' => false,
            'is_deleted' => false,
            'created_by' => $manComUser->id,
        ]);

        ProjectRequirement::create([
            'project_id' => $project->id,
            'group_name' => '4. ManCom JV Project Decision',
            'item_name' => 'Study evaluation, recommendation, and ManCom presentation material',
            'source_document' => 'SPG JV Tracking Sheet',
            'track' => 'spg_jv',
            'owner_type' => 'internal',
            'visibility' => 'internal_only',
            'soi_section' => 'management_review',
            'gate_step' => 'spg_jv_mancom_project_decision',
            'is_required' => true,
            'is_applicable' => true,
            'svf_only' => false,
            'status' => 'pending',
            'sort_order' => 10,
        ]);

        ProjectRequirement::create([
            'project_id' => $project->id,
            'group_name' => 'Legacy Internal Evaluation / Endorsement',
            'item_name' => 'Legacy broad ManCom package',
            'source_document' => 'Legacy migration guard',
            'track' => 'spg_jv',
            'owner_type' => 'internal',
            'visibility' => 'internal_only',
            'soi_section' => 'management_review',
            'gate_step' => 'mancom',
            'is_required' => true,
            'is_applicable' => true,
            'svf_only' => false,
            'status' => 'pending',
            'sort_order' => 20,
        ]);

        $approval = ProjectApproval::create([
            'project_id' => $project->id,
            'workflow_id' => $workflow->id,
            'current_step_id' => $approvalToProceed->id,
            'overall_status' => 'for_mancom_review',
            'started_at' => now(),
        ]);

        Sanctum::actingAs($manComUser);

        $this->postJson("/api/approvals/{$approval->id}/approve", [
            'status' => 'approved',
            'comments' => 'Approved to proceed with study.',
        ])->assertOk();

        $this->assertDatabaseHas('approval_step_records', [
            'project_approval_id' => $approval->id,
            'step_id' => $approvalToProceed->id,
            'approver_id' => $manComUser->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('project_approvals', [
            'id' => $approval->id,
            'current_step_id' => $projectDecision->id,
        ]);
    }

    public function test_spg_jv_mancom_project_decision_blocks_only_its_specific_artifacts(): void
    {
        $manComRole = Role::create(['name' => 'ManCom', 'description' => 'Management Committee', 'is_system_role' => true]);

        $workflow = ApprovalWorkflow::create([
            'name' => 'SPG Joint Venture Project Approval',
            'description' => 'JV flow',
            'project_type_id' => null,
            'is_active' => true,
        ]);

        $projectDecision = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 4,
            'role_id' => $manComRole->id,
            'step_name' => 'ManCom JV Project Decision',
            'soi_section' => 'management_review',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $stage = ProjectStage::create(['name' => 'Management Review', 'sequence_order' => 3, 'description' => 'Management review', 'is_active' => true]);
        $status = ProjectStatus::create(['name' => 'For ManCom Review', 'color_code' => '#2563EB', 'is_active' => true]);
        $type = ProjectType::create(['name' => 'Joint Venture', 'description' => 'JV']);
        $industry = Industry::create(['name' => 'Infrastructure', 'description' => 'Infrastructure']);
        $sector = Sector::create(['name' => 'Public', 'description' => 'Public']);

        $manComUser = User::create([
            'username' => 'mancom-decision',
            'email' => 'mancom-decision@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'ManCom',
            'last_name' => 'Decision',
            'default_role_id' => $manComRole->id,
            'is_active' => true,
        ]);

        $project = Project::create([
            'project_code' => 'SPG-2026-DECISION',
            'title' => 'SPG Project Decision',
            'description' => 'Later SPG JV ManCom step.',
            'project_type_id' => $type->id,
            'industry_id' => $industry->id,
            'sector_id' => $sector->id,
            'currency' => 'PHP',
            'current_stage_id' => $stage->id,
            'status_id' => $status->id,
            'proposal_date' => '2026-06-18',
            'process_track' => 'spg_jv',
            'is_svf' => false,
            'is_archived' => false,
            'is_deleted' => false,
            'created_by' => $manComUser->id,
        ]);

        ProjectRequirement::create([
            'project_id' => $project->id,
            'group_name' => '4. ManCom JV Project Decision',
            'item_name' => 'Study evaluation, recommendation, and ManCom presentation material',
            'source_document' => 'SPG JV Tracking Sheet',
            'track' => 'spg_jv',
            'owner_type' => 'internal',
            'visibility' => 'internal_only',
            'soi_section' => 'management_review',
            'gate_step' => 'spg_jv_mancom_project_decision',
            'is_required' => true,
            'is_applicable' => true,
            'svf_only' => false,
            'status' => 'pending',
            'sort_order' => 10,
        ]);

        ProjectRequirement::create([
            'project_id' => $project->id,
            'group_name' => '7. JV Partner Selection and Award',
            'item_name' => 'JV-SC selection proceedings and recommendation to award',
            'source_document' => 'SPG JV Tracking Sheet',
            'track' => 'spg_jv',
            'owner_type' => 'internal',
            'visibility' => 'internal_only',
            'soi_section' => 'board_approval',
            'gate_step' => 'spg_jv_final_award',
            'is_required' => true,
            'is_applicable' => true,
            'svf_only' => false,
            'status' => 'pending',
            'sort_order' => 20,
        ]);

        $approval = ProjectApproval::create([
            'project_id' => $project->id,
            'workflow_id' => $workflow->id,
            'current_step_id' => $projectDecision->id,
            'overall_status' => 'for_mancom_review',
            'started_at' => now(),
        ]);

        Sanctum::actingAs($manComUser);

        $this->postJson("/api/approvals/{$approval->id}/approve", [
            'status' => 'approved',
            'comments' => 'Approve the JV project.',
        ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'Study evaluation, recommendation, and ManCom presentation material',
            ])
            ->assertJsonMissing([
                'JV-SC selection proceedings and recommendation to award',
            ]);
    }

    public function test_spg_jv_workflow_sections_keep_neda_and_selection_in_board_approval(): void
    {
        $this->seed(ApprovalWorkflowSeeder::class);

        $workflow = ApprovalWorkflow::where('name', 'SPG Joint Venture Project Approval')->firstOrFail();
        $sections = ApprovalStep::where('workflow_id', $workflow->id)
            ->orderBy('step_order')
            ->pluck('soi_section', 'step_order')
            ->all();

        $this->assertSame('intake', $sections[1]);
        $this->assertSame('management_review', $sections[2]);
        $this->assertSame('due_diligence', $sections[3]);
        $this->assertSame('management_review', $sections[4]);
        $this->assertSame('board_approval', $sections[5]);
        $this->assertSame('board_approval', $sections[6]);
        $this->assertSame('board_approval', $sections[7]);
        $this->assertSame('board_approval', $sections[8]);
        $this->assertSame('board_approval', $sections[9]);
        $this->assertSame('agreement_fund_release', $sections[10]);
    }

    public function test_return_for_revision_routes_to_previous_soi_step_not_proponent_submission(): void
    {
        $proponentRole = Role::create(['name' => 'Proponent', 'description' => 'External proponent', 'is_system_role' => true]);
        $projectOfficerRole = Role::create(['name' => 'Project Officer', 'description' => 'Project Officer', 'is_system_role' => true]);
        $workgroupRole = Role::create(['name' => 'Workgroup Head', 'description' => 'Workgroup Head', 'is_system_role' => true]);

        $workflow = ApprovalWorkflow::create([
            'name' => 'NDC BDG Investment Approval',
            'description' => 'BDG SOI flow',
            'project_type_id' => null,
            'is_active' => true,
        ]);

        $submitterStep = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'role_id' => $proponentRole->id,
            'step_name' => 'Proponent Submission',
            'soi_section' => 'intake',
            'is_required' => true,
            'can_skip' => false,
        ]);

        ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'role_id' => $projectOfficerRole->id,
            'step_name' => 'Pre-screening / KYC and LOI Receipt',
            'soi_section' => 'intake',
            'is_required' => true,
            'can_skip' => false,
        ]);

        ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 3,
            'role_id' => $projectOfficerRole->id,
            'step_name' => 'Response Letter and Completeness Check',
            'soi_section' => 'requirements',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $dueDiligenceStep = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 4,
            'role_id' => $projectOfficerRole->id,
            'step_name' => 'Validation, Triangulation, and Due Diligence',
            'soi_section' => 'due_diligence',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $workgroupStep = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 5,
            'role_id' => $workgroupRole->id,
            'step_name' => 'AGM / Workgroup Review',
            'soi_section' => 'management_review',
            'is_required' => true,
            'can_skip' => false,
        ]);

        $dueDiligenceStage = ProjectStage::create([
            'name' => 'Due Diligence',
            'sequence_order' => 3,
            'description' => 'Due diligence',
            'is_active' => true,
        ]);
        ProjectStage::create([
            'name' => 'Management Review',
            'sequence_order' => 4,
            'description' => 'Management review',
            'is_active' => true,
        ]);

        $status = ProjectStatus::create(['name' => 'For Workgroup Review', 'color_code' => '#2563EB', 'is_active' => true]);
        ProjectStatus::create(['name' => 'Returned for Revision', 'color_code' => '#DC2626', 'is_active' => true]);

        $type = ProjectType::create(['name' => 'Business Development', 'description' => 'BDG']);
        $industry = Industry::create(['name' => 'Energy', 'description' => 'Energy']);
        $sector = Sector::create(['name' => 'Private', 'description' => 'Private']);

        $workgroupUser = User::create([
            'username' => 'wgh-return',
            'email' => 'wgh-return@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Workgroup',
            'last_name' => 'Head',
            'default_role_id' => $workgroupRole->id,
            'is_active' => true,
        ]);

        $project = Project::create([
            'project_code' => 'BDG-2026-RETURN',
            'title' => 'Returned Stage Routing',
            'description' => 'Return should go to the prior SOI step.',
            'project_type_id' => $type->id,
            'industry_id' => $industry->id,
            'sector_id' => $sector->id,
            'currency' => 'PHP',
            'current_stage_id' => $dueDiligenceStage->id,
            'status_id' => $status->id,
            'proposal_date' => '2026-06-19',
            'process_track' => 'bdg_investment',
            'is_svf' => false,
            'is_archived' => false,
            'is_deleted' => false,
            'created_by' => $workgroupUser->id,
        ]);

        $approval = ProjectApproval::create([
            'project_id' => $project->id,
            'workflow_id' => $workflow->id,
            'current_step_id' => $workgroupStep->id,
            'overall_status' => 'for_workgroup_review',
            'started_at' => now(),
        ]);

        Sanctum::actingAs($workgroupUser);

        $this->postJson("/api/approvals/{$approval->id}/reject", [
            'comments' => 'Please revise due diligence findings before Workgroup review.',
        ])->assertOk();

        $this->assertDatabaseHas('approval_step_records', [
            'project_approval_id' => $approval->id,
            'step_id' => $workgroupStep->id,
            'approver_id' => $workgroupUser->id,
            'status' => 'returned',
        ]);

        $this->assertDatabaseHas('project_approvals', [
            'id' => $approval->id,
            'overall_status' => 'returned',
            'current_step_id' => $dueDiligenceStep->id,
        ]);

        $this->assertDatabaseMissing('project_approvals', [
            'id' => $approval->id,
            'current_step_id' => $submitterStep->id,
        ]);
    }
}
