<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardDecisionSupportApiTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $officerRole;
    private User $admin;
    private User $officer;
    private User $otherOfficer;
    private ProjectStage $stage;
    private ProjectStatus $status;
    private Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'description' => 'Portfolio admin', 'is_system_role' => true]);
        $this->officerRole = Role::create(['name' => 'Project Officer', 'description' => 'Officer', 'is_system_role' => true]);
        $this->admin = $this->createUser('dashboard-admin', $this->adminRole);
        $this->officer = $this->createUser('dashboard-officer', $this->officerRole);
        $this->otherOfficer = $this->createUser('other-officer', $this->officerRole);
        $this->stage = ProjectStage::create(['name' => 'Intake', 'sequence_order' => 1, 'is_active' => true]);
        $this->status = ProjectStatus::create(['name' => 'Active', 'color_code' => '#2563eb', 'is_active' => true]);
        $this->sector = Sector::create(['name' => 'Energy', 'description' => 'Energy']);
    }

    public function test_admin_gets_portfolio_decision_support_contract_and_existing_keys(): void
    {
        $project = $this->createProject('DASH-ADMIN', $this->otherOfficer, [
            'monitoring_status' => 'active',
            'monitoring_submission_status' => 'submitted',
            'monitoring_due_date' => today()->addDays(5),
        ]);
        $this->createApproval($project, $this->adminRole);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/dashboard/stats?scope=portfolio&due_window=7');

        $response->assertOk()
            ->assertJsonPath('total_projects', 1)
            ->assertJsonPath('filters.role.mode', 'portfolio')
            ->assertJsonPath('filters.applied.scope', 'portfolio')
            ->assertJsonCount(2, 'decision_queue')
            ->assertJsonStructure([
                'pending_actions',
                'projects_by_stage',
                'monitoring_summary',
                'decision_queue',
                'risk_projects',
                'workload' => ['mode', 'totals', 'officers'],
                'monitoring_compliance' => ['active', 'due_in_window', 'overdue', 'compliance_rate', 'projects'],
                'data_quality' => ['total_projects', 'projects_with_issues', 'completeness_rate', 'records'],
                'filters' => ['applied', 'available_years', 'due_windows', 'scopes', 'sectors', 'stages', 'role'],
            ]);
    }

    public function test_officer_scope_cannot_be_escalated_to_unassigned_portfolio_projects(): void
    {
        $mine = $this->createProject('DASH-MINE', $this->officer);
        $hidden = $this->createProject('DASH-HIDDEN', $this->otherOfficer);
        $this->createApproval($mine, $this->officerRole);
        $this->createApproval($hidden, $this->officerRole);

        Sanctum::actingAs($this->officer);

        $response = $this->getJson('/api/dashboard/stats?scope=portfolio');

        $response->assertOk()
            ->assertJsonPath('total_projects', 1)
            ->assertJsonPath('filters.applied.scope', 'mine')
            ->assertJsonPath('filters.role.mode', 'officer')
            ->assertJsonCount(1, 'decision_queue');
        $this->assertSame($mine->id, $response->json('decision_queue.0.project_id'));
    }

    public function test_year_filter_uses_application_fallback_dates_and_month_completion_uses_current_year(): void
    {
        $current = $this->createProject('DASH-CURRENT', $this->officer, [
            'date_of_application' => now()->startOfYear()->addMonth()->toDateString(),
            'actual_completion_date' => now()->startOfMonth()->toDateString(),
        ]);
        $this->createProject('DASH-PRIOR', $this->officer, [
            'date_of_application' => null,
            'proposal_date' => now()->subYear()->startOfYear()->addMonth()->toDateString(),
            'actual_completion_date' => now()->subYear()->startOfMonth()->toDateString(),
        ]);

        Sanctum::actingAs($this->officer);

        $response = $this->getJson('/api/dashboard/stats?year=' . now()->year);

        $response->assertOk()
            ->assertJsonPath('total_projects', 1)
            ->assertJsonPath('completed_this_month', 1);
        $this->assertSame($current->id, $response->json('data_quality.records.0.project_id'));
    }

    public function test_due_window_excludes_overdue_monitoring_from_upcoming_count_and_handles_null_dates(): void
    {
        $this->createProject('DASH-UPCOMING', $this->officer, [
            'monitoring_status' => 'active',
            'monitoring_submission_status' => 'draft',
            'monitoring_due_date' => today()->addDays(10),
        ]);
        $this->createProject('DASH-OVERDUE', $this->officer, [
            'monitoring_status' => 'active',
            'monitoring_submission_status' => 'draft',
            'monitoring_due_date' => today()->subDay(),
        ]);
        $this->createProject('DASH-NULL', $this->officer, [
            'monitoring_status' => 'active',
            'monitoring_submission_status' => 'draft',
            'monitoring_due_date' => null,
            'sector_id' => null,
            'project_officer_id' => null,
        ]);

        Sanctum::actingAs($this->officer);

        $response = $this->getJson('/api/dashboard/stats?due_window=14');

        $response->assertOk()
            ->assertJsonPath('attention_summary.monitoring_due', 1)
            ->assertJsonPath('monitoring_compliance.due_in_window', 1)
            ->assertJsonPath('monitoring_compliance.overdue', 1)
            ->assertJsonPath('monitoring_compliance.missing_due_date', 1)
            ->assertJsonPath('data_quality.projects_with_issues', 3);
    }

    public function test_dashboard_filter_validation_returns_field_errors(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/dashboard/stats?due_window=90&year=1900')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_window', 'year']);
    }

    private function createUser(string $username, Role $role): User
    {
        return User::create([
            'username' => $username,
            'email' => $username . '@example.com',
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
            'title' => 'Dashboard project ' . $code,
            'description' => null,
            'process_track' => 'bdg_investment',
            'date_of_application' => now()->toDateString(),
            'sector_id' => $this->sector->id,
            'current_stage_id' => $this->stage->id,
            'status_id' => $this->status->id,
            'project_officer_id' => $creator->id,
            'workgroup_head_id' => null,
            'currency' => 'PHP',
            'is_archived' => false,
            'is_deleted' => false,
            'created_by' => $creator->id,
        ], $overrides));
    }

    private function createApproval(Project $project, Role $role): ProjectApproval
    {
        $workflow = ApprovalWorkflow::create([
            'name' => 'Dashboard workflow ' . $project->id,
            'description' => 'Dashboard test',
            'is_active' => true,
        ]);
        $step = ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'role_id' => $role->id,
            'step_name' => 'Portfolio decision',
            'is_required' => true,
            'can_skip' => false,
        ]);

        return ProjectApproval::create([
            'project_id' => $project->id,
            'workflow_id' => $workflow->id,
            'current_step_id' => $step->id,
            'overall_status' => 'for_approval',
            'started_at' => now()->subDays(8),
        ]);
    }
}
