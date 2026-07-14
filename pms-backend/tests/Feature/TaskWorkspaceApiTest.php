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

class TaskWorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'name' => 'SuperAdmin',
            'description' => 'Task workspace administrator',
            'is_system_role' => true,
        ]);
        $this->user = User::create([
            'username' => 'task-admin',
            'email' => 'task-admin@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Task',
            'last_name' => 'Admin',
            'default_role_id' => $role->id,
            'is_active' => true,
        ]);

        $stage = ProjectStage::create(['name' => 'Intake', 'sequence_order' => 1, 'is_active' => true]);
        $status = ProjectStatus::create(['name' => 'Approved', 'color_code' => '#2563eb', 'is_active' => true]);
        $type = ProjectType::create(['name' => 'Infrastructure', 'description' => 'Infrastructure']);
        $industry = Industry::create(['name' => 'Energy', 'description' => 'Energy']);
        $sector = Sector::create(['name' => 'Public', 'description' => 'Public']);

        $this->project = Project::create([
            'project_code' => 'BDG-2026-TASKS',
            'title' => 'Task workspace project',
            'description' => 'Task workspace API test',
            'process_track' => 'bdg_investment',
            'origin_track' => 'bdg_investment',
            'lifecycle_phase' => 'implementation_monitoring',
            'project_type_id' => $type->id,
            'industry_id' => $industry->id,
            'sector_id' => $sector->id,
            'currency' => 'PHP',
            'current_stage_id' => $stage->id,
            'status_id' => $status->id,
            'proponent_name' => 'Sample Proponent',
            'proponent_email' => 'proponent@example.com',
            'created_by' => $this->user->id,
            'is_svf' => false,
            'is_archived' => false,
            'is_deleted' => false,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_list_returns_filtered_summary_facets_permissions_and_pagination(): void
    {
        $this->createTask('Urgent intake', 'pending', 'critical', 'intake', now()->subDay()->toDateString());
        $this->createTask('Normal review', 'in_progress', 'normal', 'management_review');
        $this->createTask('Completed review', 'completed', 'high', 'management_review');

        $response = $this->getJson('/api/tasks?project_id='.$this->project->id.'&search=review&per_page=1');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.in_progress', 1)
            ->assertJsonPath('summary.completed', 1)
            ->assertJsonPath('permissions.can_create', true)
            ->assertJsonCount(1, 'data');

        $this->assertContains('management review', collect($response->json('facets.soi_sections'))->pluck('value')->all());
        $this->assertSame($this->project->id, $response->json('facets.projects.0.id'));
    }

    public function test_board_lanes_have_independent_pagination(): void
    {
        foreach (range(1, 3) as $index) {
            $this->createTask("Pending {$index}", 'pending', 'normal', 'intake');
            $this->createTask("Active {$index}", 'in_progress', 'high', 'requirements');
        }
        $this->createTask('Done', 'completed', 'normal', 'completion');

        $response = $this->getJson('/api/tasks?view=board&project_id='.$this->project->id.'&lane_per_page=2&lane_page_pending=2');

        $response->assertOk()
            ->assertJsonPath('board.pending.meta.current_page', 2)
            ->assertJsonPath('board.pending.meta.total', 3)
            ->assertJsonPath('board.in_progress.meta.current_page', 1)
            ->assertJsonPath('board.in_progress.meta.total', 3)
            ->assertJsonCount(1, 'board.pending.data')
            ->assertJsonCount(2, 'board.in_progress.data')
            ->assertJsonPath('summary.total', 7);
    }

    public function test_overdue_filter_is_boolean_and_summary_matches_the_result_set(): void
    {
        $this->createTask('Late task', 'pending', 'urgent', 'intake', now()->subDay()->toDateString());
        $this->createTask('Future task', 'pending', 'normal', 'intake', now()->addDay()->toDateString());
        $this->createTask('Late but done', 'completed', 'normal', 'completion', now()->subDay()->toDateString());

        $response = $this->getJson('/api/tasks?project_id='.$this->project->id.'&overdue=true');

        $response->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.overdue', 1)
            ->assertJsonPath('data.0.title', 'Late task');
    }

    public function test_urgent_filter_includes_critical_and_urgent_priorities(): void
    {
        $this->createTask('Critical task', 'pending', 'critical', 'intake');
        $this->createTask('Urgent task', 'in_progress', 'urgent', 'requirements');
        $this->createTask('Normal task', 'pending', 'normal', 'intake');

        $response = $this->getJson('/api/tasks?project_id='.$this->project->id.'&urgent=true');

        $response->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.urgent', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_completion_endpoint_completes_and_reopens_with_history(): void
    {
        $task = $this->createTask('Commission facility', 'in_progress', 'high', 'commissioning');
        $task->update(['progress_percentage' => 45]);

        $this->patchJson("/api/tasks/{$task->id}/completion", ['completed' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.progress_percentage', 100);

        $this->patchJson("/api/tasks/{$task->id}/completion", ['completed' => false])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.progress_percentage', 45);

        $this->assertDatabaseCount('task_status_history', 2);
    }

    public function test_development_and_archived_soi_tasks_are_excluded(): void
    {
        $visible = $this->createTask('Build foundation', 'pending', 'high', 'construction');
        $legacy = $this->createTask('Prepare ManCom brief', 'pending', 'normal', 'management_review');
        $legacy->update(['task_scope' => 'legacy_soi', 'archived_at' => now(), 'archive_reason' => 'Legacy SOI task']);

        $response = $this->getJson('/api/tasks?project_id='.$this->project->id);

        $response->assertOk()->assertJsonPath('summary.total', 1)->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_parent_completion_rolls_up_from_checklist_items(): void
    {
        $parent = $this->createTask('Build facility', 'pending', 'high', 'construction');
        $first = $this->createTask('Complete civil works', 'pending', 'high', 'construction');
        $second = $this->createTask('Complete electrical works', 'pending', 'high', 'construction');
        $first->update(['parent_task_id' => $parent->id]);
        $second->update(['parent_task_id' => $parent->id]);

        $this->patchJson("/api/tasks/{$parent->id}/completion", ['completed' => true])->assertUnprocessable();
        $this->patchJson("/api/tasks/{$first->id}/completion", ['completed' => true])->assertOk();
        $this->assertSame('in_progress', $parent->fresh()->status);

        $this->patchJson("/api/tasks/{$second->id}/completion", ['completed' => true])->assertOk();
        $this->assertSame('completed', $parent->fresh()->status);

        $this->patchJson("/api/tasks/{$parent->id}/completion", ['completed' => false])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Reopen a checklist item to reopen its parent task.');

        $this->patchJson("/api/tasks/{$first->id}/completion", ['completed' => false])->assertOk();
        $this->assertSame('in_progress', $parent->fresh()->status);
    }

    private function createTask(
        string $title,
        string $status,
        string $priority,
        string $section,
        ?string $dueDate = null
    ): Task {
        return Task::create([
            'project_id' => $this->project->id,
            'title' => $title,
            'task_type' => 'implementation',
            'soi_section' => null,
            'task_scope' => 'implementation',
            'workstream' => str_replace('_', ' ', $section),
            'assigned_to' => $this->user->id,
            'assigned_by' => $this->user->id,
            'due_date' => $dueDate,
            'status' => $status,
            'progress_percentage' => $status === 'completed' ? 100 : 0,
            'priority' => $priority,
            'is_deleted' => false,
        ]);
    }
}
