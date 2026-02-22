<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Industry;
use App\Models\Permission;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\ProjectType;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectApprovalWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_project_and_get_pending_approval(): void
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

        $createPermission = Permission::create([
            'name' => 'projects.create',
            'resource' => 'projects',
            'action' => 'create',
            'description' => 'Create projects',
        ]);

        DB::table('role_permissions')->insert([
            'role_id' => $projectOfficerRole->id,
            'permission_id' => $createPermission->id,
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
            'name' => 'Proposal',
            'sequence_order' => 1,
            'description' => 'Proposal stage',
            'is_active' => true,
        ]);

        $status = ProjectStatus::create([
            'name' => 'Pending',
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

        Sanctum::actingAs($user);

        $createResponse = $this->postJson('/api/projects', [
            'title' => 'QA Integration Project',
            'description' => 'Project created by integration test',
            'project_type_id' => $type->id,
            'industry_id' => $industry->id,
            'sector_id' => $sector->id,
            'current_stage_id' => $stage->id,
            'status_id' => $status->id,
            'proposal_date' => '2026-02-22',
            'is_svf' => false,
        ]);

        $createResponse->assertOk();
        $projectId = (int) $createResponse->json('data.id');
        $this->assertGreaterThan(0, $projectId);

        $this->assertDatabaseHas('project_approvals', [
            'project_id' => $projectId,
            'workflow_id' => $workflow->id,
            'current_step_id' => $stepTwo->id,
            'overall_status' => 'for_evaluation',
        ]);

        $pendingResponse = $this->getJson('/api/approvals/pending');
        $pendingResponse->assertOk();

        $pendingProjectIds = collect($pendingResponse->json('data', []))
            ->pluck('project_id')
            ->all();

        $this->assertContains($projectId, $pendingProjectIds);
    }
}
