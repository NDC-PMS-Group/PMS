<?php

namespace Tests\Feature;

use App\Models\Industry;
use App\Models\DefaultTask;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\ProjectType;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectTaskTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskDeadlineGovernanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_extend_a_reached_deadline_with_a_reason(): void
    {
        [$administrator, $task] = $this->deadlineFixture();
        Sanctum::actingAs($administrator);

        $response = $this->patchJson("/api/tasks/{$task->id}/deadline", [
            'action' => 'extend',
            'extension_date' => today()->addDays(7)->toDateString(),
            'reason' => 'The external validation report requires additional review time.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.due_date', today()->addDays(7)->toDateString())
            ->assertJsonPath('data.deadline_reached', false);

        $event = $task->statusHistory()->where('event_type', 'deadline_extended')->firstOrFail();
        $this->assertSame(today()->subDay()->toDateString(), $event->previous_due_date->toDateString());
        $this->assertSame(today()->addDays(7)->toDateString(), $event->new_due_date->toDateString());
        $this->assertSame($administrator->id, $event->changed_by);
        $this->assertDatabaseHas('task_status_history', [
            'task_id' => $task->id,
            'event_type' => 'deadline_extended',
            'changed_by' => $administrator->id,
        ]);
    }

    public function test_reached_deadline_cannot_be_completed_from_checkbox_without_resolution_details(): void
    {
        [$administrator, $task] = $this->deadlineFixture();
        Sanctum::actingAs($administrator);

        $this->patchJson("/api/tasks/{$task->id}/completion", ['completed' => true])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'TASK_DEADLINE_REQUIRES_RESOLUTION');

        $this->patchJson("/api/tasks/{$task->id}/deadline", [
            'action' => 'complete',
            'actual_completion_date' => today()->toDateString(),
            'reason' => 'The deliverable was accepted today after final verification.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.completion_date', today()->toDateString());

        $event = $task->statusHistory()->where('event_type', 'deadline_completed')->firstOrFail();
        $this->assertSame(today()->toDateString(), $event->actual_completion_date->toDateString());
        $this->assertSame($administrator->id, $event->changed_by);
        $this->assertDatabaseHas('task_status_history', [
            'task_id' => $task->id,
            'event_type' => 'deadline_completed',
            'changed_by' => $administrator->id,
        ]);
    }

    public function test_deadline_command_alerts_administrators_only_once_per_due_date(): void
    {
        [$administrator, $task] = $this->deadlineFixture();
        $task->update(['due_date' => today()->toDateString()]);

        $this->artisan('tasks:alert-reached-deadlines')->assertSuccessful();
        $this->artisan('tasks:alert-reached-deadlines')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $administrator->id,
            'type' => 'task_deadline_reached',
            'related_entity_id' => $task->id,
        ]);
        $this->assertDatabaseCount('task_status_history', 2);
        $this->assertDatabaseHas('task_deadline_notifications', [
            'task_id' => $task->id,
            'due_date' => today()->toDateString(),
            'reminder_key' => 'due_0',
            'event_type' => 'task_deadline_reached',
        ]);
    }

    public function test_deadline_command_sends_due_soon_email_once(): void
    {
        [$administrator, $task] = $this->deadlineFixture();
        $task->update(['due_date' => today()->addDays(3)->toDateString()]);

        $this->artisan('tasks:alert-reached-deadlines')->assertSuccessful();
        $this->artisan('tasks:alert-reached-deadlines')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $administrator->id,
            'type' => 'task_deadline_due_soon',
            'related_entity_id' => $task->id,
        ]);
        $this->assertDatabaseHas('task_deadline_notifications', [
            'task_id' => $task->id,
            'due_date' => today()->addDays(3)->toDateString(),
            'reminder_key' => 'due_3',
            'event_type' => 'task_deadline_due_soon',
        ]);
        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'task_deadline_due_soon',
            'user_id' => $administrator->id,
        ]);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('task_deadline_notifications', 1);
    }

    public function test_deadline_command_repeats_overdue_alerts_weekly_without_duplicates(): void
    {
        [$administrator, $task] = $this->deadlineFixture();

        $this->artisan('tasks:alert-reached-deadlines')->assertSuccessful();
        $this->artisan('tasks:alert-reached-deadlines')->assertSuccessful();

        $this->assertDatabaseHas('task_deadline_notifications', [
            'task_id' => $task->id,
            'reminder_key' => 'overdue_1',
            'event_type' => 'task_deadline_overdue',
        ]);
        $this->assertDatabaseCount('notifications', 1);

        $this->travel(6)->days();
        $this->artisan('tasks:alert-reached-deadlines')->assertSuccessful();
        $this->artisan('tasks:alert-reached-deadlines')->assertSuccessful();

        $this->assertDatabaseHas('task_deadline_notifications', [
            'task_id' => $task->id,
            'reminder_key' => 'overdue_7',
            'event_type' => 'task_deadline_overdue',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $administrator->id,
            'type' => 'task_deadline_overdue',
            'related_entity_id' => $task->id,
        ]);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('task_deadline_notifications', 2);
    }

    public function test_template_day_offset_is_anchored_to_project_application_date(): void
    {
        [$administrator, $existingTask] = $this->deadlineFixture();
        $project = $existingTask->project;
        $project->update([
            'date_of_application' => '2026-07-01',
            'proposal_date' => '2026-07-03',
            'start_date' => '2026-08-01',
        ]);
        $template = DefaultTask::create([
            'track' => 'bdg_investment',
            'title' => 'Application-linked screening task',
            'description' => 'Due ten days from application.',
            'task_type' => 'intake',
            'soi_section' => 'intake',
            'assigned_role' => 'Project Officer',
            'days' => 10,
            'priority' => 'high',
            'is_milestone' => false,
            'sort_order' => 10,
        ]);

        app(ProjectTaskTemplateService::class)->sync($project->fresh(), 'bdg_investment', $administrator);

        $created = Task::query()->where('template_source', "soi:bdg_investment:{$template->id}")->firstOrFail();
        $this->assertSame('2026-07-01', $created->start_date->toDateString());
        $this->assertSame('2026-07-11', $created->due_date->toDateString());
    }

    private function deadlineFixture(): array
    {
        $role = Role::create([
            'name' => 'Admin',
            'description' => 'Administrator',
            'is_system_role' => true,
        ]);
        $administrator = User::create([
            'username' => 'deadline-admin',
            'email' => 'deadline-admin@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Deadline',
            'last_name' => 'Admin',
            'default_role_id' => $role->id,
            'is_active' => true,
        ]);
        $stage = ProjectStage::create([
            'name' => 'Intake',
            'sequence_order' => 1,
            'is_active' => true,
        ]);
        $status = ProjectStatus::create([
            'name' => 'Ongoing',
            'color_code' => '#2563EB',
            'is_active' => true,
        ]);
        $type = ProjectType::create(['name' => 'Infrastructure']);
        $industry = Industry::create(['name' => 'Energy']);
        $sector = Sector::create(['name' => 'Public']);
        $project = Project::create([
            'project_code' => 'BDG-2026-DEADLINE',
            'title' => 'Deadline Governance Test',
            'description' => 'Task deadline test project',
            'process_track' => 'bdg_investment',
            'origin_track' => 'bdg_investment',
            'lifecycle_phase' => 'development',
            'project_type_id' => $type->id,
            'industry_id' => $industry->id,
            'sector_id' => $sector->id,
            'current_stage_id' => $stage->id,
            'status_id' => $status->id,
            'currency' => 'PHP',
            'created_by' => $administrator->id,
        ]);
        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Complete eligibility screening',
            'task_scope' => 'workflow',
            'assigned_to' => $administrator->id,
            'assigned_by' => $administrator->id,
            'start_date' => today()->subDays(10),
            'due_date' => today()->subDay(),
            'status' => 'in_progress',
            'progress_percentage' => 80,
            'priority' => 'high',
            'is_deleted' => false,
        ]);

        $task->statusHistory()->create([
            'from_status' => null,
            'to_status' => 'in_progress',
            'from_progress' => null,
            'to_progress' => 80,
            'changed_by' => $administrator->id,
            'event_type' => 'created',
            'notes' => 'Task created.',
            'changed_at' => now()->subDays(10),
        ]);

        return [$administrator, $task];
    }
}
