<?php

namespace Tests\Feature;

use App\Models\Industry;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\ProjectType;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectReportFilterApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ProjectStage $intakeStage;
    private ProjectStatus $approvedStatus;
    private ProjectType $projectType;
    private Industry $industry;
    private Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'name' => 'SuperAdmin',
            'description' => 'Can view reports',
            'is_system_role' => true,
        ]);

        $this->user = User::create([
            'username' => 'report-admin',
            'email' => 'report-admin@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Report',
            'last_name' => 'Admin',
            'default_role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->intakeStage = ProjectStage::create(['name' => 'Intake', 'sequence_order' => 1, 'is_active' => true]);
        $this->approvedStatus = ProjectStatus::create(['name' => 'Approved', 'color_code' => '#2563eb', 'is_active' => true]);
        $this->projectType = ProjectType::create(['name' => 'Infrastructure', 'description' => 'Infrastructure']);
        $this->industry = Industry::create(['name' => 'Energy', 'description' => 'Energy']);
        $this->sector = Sector::create(['name' => 'Public', 'description' => 'Public']);

        Sanctum::actingAs($this->user);
    }

    public function test_project_report_filters_cost_progress_overdue_and_gcg_reportable(): void
    {
        $reportable = $this->createProject('BDG-2026-RPT', [
            'process_track' => 'bdg_investment',
            'estimated_cost' => 5_000_000,
            'actual_cost' => 4_000_000,
            'target_completion_date' => now()->addMonth()->toDateString(),
            'financial_metrics' => ['reportable_to_gcg' => true],
        ]);
        $this->addTasks($reportable, 2, 2);

        $overdueSpg = $this->createProject('SPG-2026-LATE', [
            'process_track' => 'spg_jv',
            'estimated_cost' => 1_000_000,
            'actual_cost' => 1_200_000,
            'target_completion_date' => now()->subDay()->toDateString(),
            'financial_metrics' => ['reportable_to_gcg' => false],
        ]);
        $this->addTasks($overdueSpg, 1, 0);

        $this->createProject('BDG-2026-ZERO', [
            'process_track' => 'bdg_investment',
            'estimated_cost' => 100_000,
            'actual_cost' => 80_000,
            'target_completion_date' => now()->addYear()->toDateString(),
            'financial_metrics' => ['reportable_to_gcg' => false],
        ]);

        $reportableResponse = $this->getJson('/api/reports/projects?estimated_cost_min=4000000&progress_min=50&reportable_to_gcg=true&per_page=50');
        $reportableResponse->assertOk();
        $this->assertSame(['BDG-2026-RPT'], collect($reportableResponse->json('data'))->pluck('project_code')->all());

        $overdueResponse = $this->getJson('/api/reports/projects?is_overdue=true&process_track=spg_jv&per_page=50');
        $overdueResponse->assertOk();
        $this->assertSame(['SPG-2026-LATE'], collect($overdueResponse->json('data'))->pluck('project_code')->all());
    }

    public function test_project_report_export_uses_same_filter_contract(): void
    {
        $this->createProject('BDG-2026-RPT', [
            'process_track' => 'bdg_investment',
            'estimated_cost' => 5_000_000,
            'actual_cost' => 4_000_000,
            'financial_metrics' => ['reportable_to_gcg' => true],
        ]);
        $this->createProject('BDG-2026-NOPE', [
            'process_track' => 'bdg_investment',
            'estimated_cost' => 100_000,
            'actual_cost' => 50_000,
            'financial_metrics' => ['reportable_to_gcg' => false],
        ]);

        $response = $this->get('/api/reports/projects/export?reportable_to_gcg=true&estimated_cost_min=4000000');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('content-disposition');
    }

    private function createProject(string $code, array $overrides = []): Project
    {
        return Project::create(array_merge([
            'project_code' => $code,
            'title' => 'Report project ' . $code,
            'description' => 'Report filter test',
            'process_track' => 'bdg_investment',
            'project_type_id' => $this->projectType->id,
            'industry_id' => $this->industry->id,
            'sector_id' => $this->sector->id,
            'currency' => 'PHP',
            'current_stage_id' => $this->intakeStage->id,
            'status_id' => $this->approvedStatus->id,
            'estimated_cost' => 1_000_000,
            'actual_cost' => 800_000,
            'proponent_name' => 'Sample Proponent',
            'proponent_email' => 'alvindalejoyosa30@gmail.com',
            'financial_metrics' => [],
            'is_svf' => false,
            'is_archived' => false,
            'is_deleted' => false,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    private function addTasks(Project $project, int $completed, int $open): void
    {
        if ($completed <= 0 && $open <= 0) {
            return;
        }

        if ($completed > 0) {
            foreach (range(1, $completed) as $index) {
                Task::create([
                    'project_id' => $project->id,
                    'title' => "Completed {$index}",
                    'task_type' => 'implementation',
                    'assigned_to' => $this->user->id,
                    'assigned_by' => $this->user->id,
                    'status' => 'completed',
                    'progress_percentage' => 100,
                    'priority' => 'normal',
                    'is_deleted' => false,
                ]);
            }
        }

        if ($open <= 0) {
            return;
        }

        foreach (range(1, $open) as $index) {
            Task::create([
                'project_id' => $project->id,
                'title' => "Open {$index}",
                'task_type' => 'implementation',
                'assigned_to' => $this->user->id,
                'assigned_by' => $this->user->id,
                'status' => 'in_progress',
                'progress_percentage' => 50,
                'priority' => 'normal',
                'is_deleted' => false,
            ]);
        }
    }
}
