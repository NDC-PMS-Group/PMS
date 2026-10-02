<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectFundRelease;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\Permission;
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
        $dashboardPermission = Permission::create([
            'name' => 'dashboard.view',
            'resource' => 'dashboard',
            'action' => 'view',
            'description' => 'View dashboard analytics and portfolio totals',
        ]);
        $this->adminRole->permissions()->attach($dashboardPermission);
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
                'portfolio_summary' => ['active_projects', 'pms_projects', 'legacy_projects', 'estimated_investment', 'actual_cost', 'released_funds', 'completed_ytd', 'unassigned_projects'],
                'portfolio_trend',
                'decision_aging' => ['total', 'within_7_days', 'days_7_to_13', 'days_14_plus', 'sla_breached'],
                'stage_breakdown',
                'sector_breakdown',
                'generated_at',
                'filters' => ['applied', 'available_years', 'due_windows', 'scopes', 'sectors', 'stages', 'role'],
            ]);
    }

    public function test_portfolio_users_default_to_portfolio_and_officers_default_to_mine(): void
    {
        $this->createProject('DASH-DEFAULT-MINE', $this->officer);
        $this->createProject('DASH-DEFAULT-OTHER', $this->otherOfficer);

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('filters.applied.scope', 'portfolio')
            ->assertJsonPath('filters.role.default_scope', 'portfolio')
            ->assertJsonPath('total_projects', 2);

        Sanctum::actingAs($this->officer);
        $this->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('filters.applied.scope', 'mine')
            ->assertJsonPath('filters.role.default_scope', 'mine')
            ->assertJsonPath('total_projects', 1);
    }

    public function test_source_filter_financial_summary_and_breakdowns_use_authoritative_records(): void
    {
        $pms = $this->createProject('DASH-PMS', $this->officer, [
            'estimated_cost' => 1000000,
            'actual_cost' => 900000,
        ]);
        $legacy = $this->createProject('DASH-LEGACY', $this->officer, [
            'estimated_cost' => 2500000,
            'record_type' => 'investment',
            'ndc_participation' => 750000,
            'actual_cost' => 2800000,
            'is_legacy' => true,
        ]);
        ProjectFundRelease::create([
            'project_id' => $legacy->id,
            'status' => 'released',
            'amount' => 750000,
            'release_date' => today(),
        ]);
        ProjectFundRelease::create([
            'project_id' => $legacy->id,
            'status' => 'draft',
            'amount' => 500000,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/dashboard/stats?record_source=legacy');
        $response->assertOk()
            ->assertJsonPath('filters.applied.record_source', 'legacy')
            ->assertJsonPath('portfolio_summary.active_projects', 1)
            ->assertJsonPath('portfolio_summary.pms_projects', 0)
            ->assertJsonPath('portfolio_summary.legacy_projects', 1)
            ->assertJsonPath('portfolio_summary.estimated_investment', 750000)
            ->assertJsonPath('portfolio_summary.actual_cost', 2800000)
            ->assertJsonPath('portfolio_summary.released_funds', 750000)
            ->assertJsonPath('stage_breakdown.0.count', 1)
            ->assertJsonPath('stage_breakdown.0.route.path', '/projects/legacy')
            ->assertJsonPath('risk_projects.0.route.path', '/projects/legacy');

        $this->assertSame($legacy->id, $response->json('data_quality.records.0.project_id'));
        $this->assertNotSame($pms->id, $response->json('data_quality.records.0.project_id'));
    }

    public function test_dashboard_returns_monthly_trend_and_decision_aging(): void
    {
        $project = $this->createProject('DASH-TREND', $this->officer, [
            'date_of_application' => now()->startOfMonth()->toDateString(),
            'actual_completion_date' => now()->startOfMonth()->toDateString(),
        ]);
        $approval = $this->createApproval($project, $this->adminRole);
        $approval->update([
            'started_at' => now()->subDays(18),
            'current_step_started_at' => now()->subDays(18),
            'sla_due_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/dashboard/stats');
        $response->assertOk()
            ->assertJsonCount(12, 'portfolio_trend')
            ->assertJsonPath('decision_aging.total', 1)
            ->assertJsonPath('decision_aging.days_14_plus', 1)
            ->assertJsonPath('decision_aging.sla_breached', 1);

        $currentMonth = collect($response->json('portfolio_trend'))->firstWhere('month', now()->format('Y-m'));
        $this->assertSame(1, $currentMonth['intakes']);
        $this->assertSame(1, $currentMonth['completions']);
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

    public function test_proponent_defaults_to_linked_projects_only(): void
    {
        $proponentRole = Role::create(['name' => 'Proponent', 'description' => 'External proponent', 'is_system_role' => true]);
        $proponent = $this->createUser('dashboard-proponent', $proponentRole);
        $linked = $this->createProject('DASH-LINKED', $this->officer, [
            'proponent_email' => $proponent->email,
            'monitoring_status' => 'active',
            'monitoring_due_date' => today()->addDays(5),
        ]);
        $this->createProject('DASH-NOT-LINKED', $this->otherOfficer);

        Sanctum::actingAs($proponent);

        $response = $this->getJson('/api/dashboard/stats?scope=portfolio');

        $response->assertOk()
            ->assertJsonPath('filters.applied.scope', 'mine')
            ->assertJsonPath('filters.role.default_scope', 'mine')
            ->assertJsonPath('total_projects', 1)
            ->assertJsonPath('monitoring_compliance.projects.0.project_id', $linked->id)
            ->assertJsonPath('monitoring_compliance.projects.0.is_legacy', false);
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

        $this->getJson('/api/dashboard/stats?due_window=90&year=1900&record_source=archive')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_window', 'year', 'record_source']);
    }


    public function test_uat_classification_and_financial_totals_do_not_include_total_project_cost(): void
    {
        $this->createProject('UAT-PROJECT', $this->officer, ['record_type' => 'project', 'estimated_cost' => 9000000, 'ndc_participation' => 9000000]);
        $this->createProject('UAT-UNKNOWN', $this->officer, ['estimated_cost' => 8000000]);
        $investment = $this->createProject('UAT-INVESTMENT', $this->officer, ['record_type' => 'investment', 'estimated_cost' => 7000000, 'ndc_participation' => 1000000]);
        $this->createProject('UAT-USD', $this->officer, ['record_type' => 'investment', 'estimated_cost' => 5000000, 'ndc_participation' => 50, 'currency' => 'USD']);
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/dashboard/stats')->assertOk()
            ->assertJsonPath('portfolio_summary.active_ndc_projects', 1)
            ->assertJsonPath('portfolio_summary.investments_under_evaluation', 2)
            ->assertJsonPath('portfolio_summary.investment_portfolio', 0)
            ->assertJsonPath('portfolio_summary.unclassified_records', 1)
            ->assertJsonPath('portfolio_summary.estimated_investment', 1000000)
            ->assertJsonPath('portfolio_summary.ndc_investment_by_currency.USD', 50);
        $this->assertSame('under_evaluation', $investment->investment_status);
    }

    public function test_uat_portfolio_requires_final_board_approval_and_actual_release(): void
    {
        $project = $this->createProject('UAT-LIFECYCLE', $this->officer, ['record_type' => 'investment']);
        $board = Role::create(['name' => 'Board', 'is_system_role' => true]);
        $workflow = ApprovalWorkflow::create(['name' => 'UAT origin', 'workflow_key' => 'uat_origin', 'workflow_group' => 'origin', 'is_active' => true]);
        $first = ApprovalStep::create(['workflow_id' => $workflow->id, 'step_order' => 1, 'role_id' => $board->id, 'step_name' => 'Initial Board approval']);
        $final = ApprovalStep::create(['workflow_id' => $workflow->id, 'step_order' => 2, 'role_id' => $board->id, 'step_name' => 'Final Board approval']);
        $approval = ProjectApproval::create(['project_id' => $project->id, 'workflow_id' => $workflow->id, 'current_step_id' => $first->id, 'overall_status' => 'for_board_approval', 'started_at' => now()]);
        \App\Models\ApprovalStepRecord::create(['project_approval_id' => $approval->id, 'step_id' => $first->id, 'approver_id' => $this->admin->id, 'status' => 'approved', 'reviewed_at' => now()]);
        $this->assertSame('under_evaluation', $project->fresh()->investment_status);
        $record = \App\Models\ApprovalStepRecord::create(['project_approval_id' => $approval->id, 'step_id' => $final->id, 'approver_id' => $this->admin->id, 'status' => 'approved', 'reviewed_at' => now()]);
        $this->assertSame('board_approved', $project->fresh()->investment_status);
        $release = ProjectFundRelease::create(['project_id' => $project->id, 'status' => 'draft', 'amount' => 100]);
        $this->assertSame('board_approved', $project->fresh()->investment_status);
        $release->update(['status' => 'released']);
        $this->assertSame('portfolio', $project->fresh()->investment_status);
        $this->assertSame(1, Project::classified('investment', 'portfolio')->count());
        $this->assertSame(0, Project::classified('investment', 'under_evaluation')->count());
        $record->update(['status' => 'rejected']);
        $this->assertSame('under_evaluation', $project->fresh()->investment_status);
        $this->assertSame(0, Project::classified('investment', 'portfolio')->count());
        $approval->update(['overall_status' => 'rejected']);
        $this->assertSame('not_proceeding', $project->fresh()->investment_status);
        $this->assertSame(0, Project::classified('investment', 'under_evaluation')->count());
        $this->assertSame(1, Project::classified('investment', 'not_proceeding')->count());
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
