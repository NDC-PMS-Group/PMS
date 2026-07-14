<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\Industry;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\ProjectType;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImplementationLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'SuperAdmin', 'description' => 'Administrator', 'is_system_role' => true]);
        $this->user = User::create([
            'username' => 'implementation-admin',
            'email' => 'implementation@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Implementation',
            'last_name' => 'Admin',
            'default_role_id' => $role->id,
            'is_active' => true,
        ]);

        $intake = ProjectStage::create(['name' => 'Intake', 'sequence_order' => 1, 'is_active' => true]);
        ProjectStage::create(['name' => 'Implementation & Monitoring', 'sequence_order' => 7, 'is_active' => true]);
        $approved = ProjectStatus::create(['name' => 'Approved', 'color_code' => '#2563eb', 'is_active' => true]);
        ProjectStatus::create(['name' => 'Implementation Ongoing', 'color_code' => '#16a34a', 'is_active' => true]);
        $type = ProjectType::create(['name' => 'Infrastructure', 'description' => 'Infrastructure']);
        $industry = Industry::create(['name' => 'Energy', 'description' => 'Energy']);
        $sector = Sector::create(['name' => 'Public', 'description' => 'Public']);

        $this->project = Project::create([
            'project_code' => 'BDG-2026-IMPLEMENT',
            'title' => 'Battery storage construction',
            'description' => 'Implementation lifecycle test',
            'process_track' => 'bdg_investment',
            'origin_track' => 'bdg_investment',
            'lifecycle_phase' => 'development',
            'project_type_id' => $type->id,
            'industry_id' => $industry->id,
            'sector_id' => $sector->id,
            'currency' => 'PHP',
            'current_stage_id' => $intake->id,
            'status_id' => $approved->id,
            'created_by' => $this->user->id,
            'is_svf' => false,
            'is_archived' => false,
            'is_deleted' => false,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_catalog_exposes_four_origins_and_separate_lifecycle_workflows(): void
    {
        $response = $this->getJson('/api/project-workflow-catalog');

        $response->assertOk()
            ->assertJsonCount(4, 'data.origins')
            ->assertJsonPath('data.origins.0.key', 'bdg_investment')
            ->assertJsonPath('data.origins.0.variants.0.key', 'svf')
            ->assertJsonPath('data.lifecycle_workflows.0.key', 'implementation_monitoring')
            ->assertJsonPath('data.lifecycle_workflows.1.key', 'divestment');
    }

    public function test_readiness_blocks_unapproved_development_project(): void
    {
        $this->getJson("/api/projects/{$this->project->id}/implementation/readiness")
            ->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.blockers.0.code', 'development_approval');
    }

    public function test_start_implementation_creates_delivery_plan_once(): void
    {
        $workflow = ApprovalWorkflow::create([
            'name' => 'NDC BDG Investment Approval',
            'description' => 'BDG development approval',
            'is_active' => true,
        ]);
        ProjectApproval::create([
            'project_id' => $this->project->id,
            'workflow_id' => $workflow->id,
            'overall_status' => 'approved',
            'started_at' => now()->subWeek(),
            'completed_at' => now(),
        ]);

        $this->postJson("/api/projects/{$this->project->id}/implementation/start")
            ->assertOk()
            ->assertJsonPath('project.lifecycle_phase', 'implementation_monitoring');

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'lifecycle_phase' => 'implementation_monitoring',
            'implementation_started_by' => $this->user->id,
        ]);
        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'task_scope' => 'implementation',
            'template_source' => 'infrastructure',
        ]);
        $taskCount = $this->project->tasks()->implementation()->active()->count();
        $this->assertGreaterThan(0, $taskCount);

        $this->postJson("/api/projects/{$this->project->id}/implementation/start")
            ->assertStatus(409);
        $this->assertSame($taskCount, $this->project->tasks()->implementation()->active()->count());
    }
}
