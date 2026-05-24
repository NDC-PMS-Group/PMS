<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $myProjectsOnly = $request->boolean('my_projects');

        $query = Task::with(['project', 'assignedTo', 'assignedBy', 'statusHistory', 'subtasks.assignedTo', 'subtasks.statusHistory']);

        if ($myProjectsOnly) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('project', function ($projectQuery) use ($user) {
                    $projectQuery->where('created_by', $user->id);
                })->orWhereHas('project.members', function ($memberQuery) use ($user) {
                    $memberQuery
                        ->where('user_id', $user->id)
                        ->whereNull('removed_at')
                        ->where('can_view', true);
                });
            });
        } elseif (!$this->hasAnyPermission($user, ['tasks.view', 'task.view', 'view_task'])) {
            $query->whereHas('project.members', function ($memberQuery) use ($user) {
                $memberQuery
                    ->where('user_id', $user->id)
                    ->whereNull('removed_at')
                    ->where('can_view', true);
            });
        }

        // Filters
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('is_milestone')) {
            $query->where('is_milestone', $request->boolean('is_milestone'));
        }

        if ($request->boolean('top_level_only')) {
            $query->whereNull('parent_task_id');
        }

        if ($request->has('overdue')) {
            $query->overdue();
        }

        $query->active(); // Only active tasks

        // Sorting
        $sortBy = $request->get('sort_by', 'smart_priority');
        $sortOrder = $request->get('sort_order', 'asc');
        if ($sortBy === 'smart_priority') {
            $query
                ->orderByRaw('CASE WHEN parent_task_id IS NULL THEN 0 ELSE 1 END')
                ->orderByRaw("CASE priority
                    WHEN 'critical' THEN 0
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'normal' THEN 4
                    WHEN 'low' THEN 5
                    ELSE 6
                END")
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_date', 'asc')
                ->orderBy('created_at', 'asc');
        } else {
            if (!in_array($sortBy, ['due_date', 'created_at', 'updated_at', 'title', 'status', 'priority', 'progress_percentage'], true)) {
                $sortBy = 'due_date';
            }
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = $request->get('per_page', 15);
        $tasks = $query->paginate($perPage);

        return TaskResource::collection($tasks);
    }

    /**
     * Store a newly created task.
     */
    public function store(StoreTaskRequest $request)
    {
        $project = Project::findOrFail($request->validated('project_id'));

        if (!$this->canEditTaskProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to create task in this project'], 403);
        }

        $payload = $request->validated();
        if (($payload['status'] ?? null) === 'completed') {
            $payload['completion_date'] = now();
            $payload['progress_percentage'] = 100;
        }

        $task = Task::create(array_merge(
            $payload,
            ['assigned_by' => auth()->id()]
        ));

        $this->recordTaskHistory(
            $task,
            null,
            $task->status,
            null,
            $task->progress_percentage,
            $request->user(),
            'created',
            'Task created.'
        );

        $this->notifyTaskAssigned($task->fresh(['project', 'assignedTo', 'assignedBy']), $request->user(), 'task_assigned');

        return new TaskResource($task->load(['project', 'assignedTo', 'assignedBy', 'statusHistory']));
    }

    /**
     * Display the specified task.
     */
    public function show(Task $task)
    {
        if (!$this->canViewTask(auth()->user(), $task)) {
            return response()->json(['message' => 'Unauthorized to view this task'], 403);
        }

        $task->load([
            'project', 'assignedTo', 'assignedBy',
            'parentTask', 'subtasks.assignedTo', 'subtasks.statusHistory', 'dependencies', 'resources', 'statusHistory'
        ]);

        return new TaskResource($task);
    }

    /**
     * Update the specified task.
     */
    public function update(UpdateTaskRequest $request, Task $task)
    {
        if (!$this->canEditTask(auth()->user(), $task)) {
            return response()->json(['message' => 'Unauthorized to update this task'], 403);
        }

        $oldAssignedTo = $task->assigned_to;
        $oldStatus = $task->status;
        $oldDueDate = $task->due_date?->toDateString();
        $oldPriority = $task->priority;
        $oldProgress = $task->progress_percentage;

        $payload = $request->validated();
        if (($payload['status'] ?? null) === 'completed' && empty($payload['completion_date']) && !$task->completion_date) {
            $payload['completion_date'] = now();
            $payload['progress_percentage'] = $payload['progress_percentage'] ?? 100;
        }

        $task->update($payload);

        $freshTask = $task->fresh(['project', 'assignedTo', 'assignedBy', 'statusHistory']);

        if ($oldStatus !== $freshTask->status) {
            $this->recordTaskHistory(
                $freshTask,
                $oldStatus,
                $freshTask->status,
                $oldProgress,
                $freshTask->progress_percentage,
                $request->user(),
                'status_changed',
                $this->statusChangeNote($oldStatus, $freshTask->status)
            );
        } elseif ($request->has('progress_percentage') && (int) $oldProgress !== (int) $freshTask->progress_percentage) {
            $this->recordTaskHistory(
                $freshTask,
                $freshTask->status,
                $freshTask->status,
                $oldProgress,
                $freshTask->progress_percentage,
                $request->user(),
                'progress_updated',
                "Progress updated to {$freshTask->progress_percentage}%."
            );
        }

        if ($request->has('assigned_to') && (int) $oldAssignedTo !== (int) $freshTask->assigned_to) {
            $this->notifyTaskAssigned($freshTask, $request->user(), 'task_reassigned');
        } elseif ($freshTask->assigned_to && $freshTask->assigned_to !== $request->user()?->id) {
            $watchedFieldsChanged = $oldStatus !== $freshTask->status
                || $oldDueDate !== $freshTask->due_date?->toDateString()
                || $oldPriority !== $freshTask->priority
                || $request->hasAny(['title', 'description']);

            if ($watchedFieldsChanged) {
                $this->notifyTaskUpdated($freshTask, $request->user());
            }
        }

        return new TaskResource($freshTask->fresh(['project', 'assignedTo', 'assignedBy', 'statusHistory']));
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Task $task)
    {
        if (!$this->canDeleteTask(auth()->user(), $task)) {
            return response()->json(['message' => 'Unauthorized to delete this task'], 403);
        }

        $task->update(['is_deleted' => true]);

        if (!$task->parent_task_id) {
            Task::where('parent_task_id', $task->id)->update(['is_deleted' => true]);
        }

        $this->notifyTaskDeleted($task->loadMissing(['project', 'assignedTo']), auth()->user());

        return response()->json(['message' => 'Task deleted successfully'], 200);
    }

    /**
     * Update task progress.
     */
    public function updateProgress(Request $request, Task $task)
    {
        if (!$this->canEditTask(auth()->user(), $task)) {
            return response()->json(['message' => 'Unauthorized to update task progress'], 403);
        }

        $request->validate([
            'progress_percentage' => 'required|integer|min:0|max:100',
        ]);

        $oldStatus = $task->status;
        $oldProgress = $task->progress_percentage;

        $task->update(['progress_percentage' => $request->progress_percentage]);

        // Auto-complete if 100%
        if ($request->progress_percentage == 100 && $task->status != 'completed') {
            $task->update([
                'status' => 'completed',
                'completion_date' => now(),
            ]);

            $this->recordTaskHistory(
                $task->fresh(),
                $oldStatus,
                'completed',
                $oldProgress,
                100,
                $request->user(),
                'status_changed',
                'Task completed by reaching 100% progress.'
            );

            $this->notifyTaskCompleted($task->fresh(['project', 'assignedTo', 'assignedBy']), $request->user());
        } elseif ($task->assigned_by && $task->assigned_by !== $request->user()?->id) {
            $this->recordTaskHistory(
                $task->fresh(),
                $task->status,
                $task->status,
                $oldProgress,
                $request->progress_percentage,
                $request->user(),
                'progress_updated',
                "Progress updated to {$request->progress_percentage}%."
            );

            $this->notifyTaskProgressUpdated($task->fresh(['project', 'assignedTo', 'assignedBy']), $request->user());
        } elseif ((int) $oldProgress !== (int) $request->progress_percentage) {
            $this->recordTaskHistory(
                $task->fresh(),
                $task->status,
                $task->status,
                $oldProgress,
                $request->progress_percentage,
                $request->user(),
                'progress_updated',
                "Progress updated to {$request->progress_percentage}%."
            );
        }

        return new TaskResource($task->fresh(['project', 'assignedTo', 'assignedBy', 'statusHistory']));
    }

    private function recordTaskHistory(
        Task $task,
        ?string $fromStatus,
        string $toStatus,
        ?int $fromProgress,
        ?int $toProgress,
        ?User $actor,
        string $eventType,
        ?string $notes = null
    ): void {
        TaskStatusHistory::create([
            'task_id' => $task->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_progress' => $fromProgress,
            'to_progress' => $toProgress,
            'changed_by' => $actor?->id,
            'event_type' => $eventType,
            'notes' => $notes,
            'changed_at' => now(),
        ]);
    }

    private function statusChangeNote(?string $fromStatus, string $toStatus): string
    {
        return match ($toStatus) {
            'in_progress' => 'Task started and moved into active work.',
            'completed' => 'Task completed.',
            'cancelled' => 'Task cancelled.',
            default => 'Task status changed from ' . ($fromStatus ?: 'none') . " to {$toStatus}.",
        };
    }

    private function hasAnyPermission(?User $user, array $permissionNames): bool
    {
        if (!$user) return false;

        foreach ($permissionNames as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    private function getActiveMember(Project $project, int $userId): ?ProjectMember
    {
        return $project->members()
            ->where('user_id', $userId)
            ->whereNull('removed_at')
            ->first();
    }

    private function canViewTask(?User $user, Task $task): bool
    {
        if (!$user) return false;

        if ($this->hasAnyPermission($user, ['tasks.view', 'task.view', 'view_task', 'projects.view', 'project.view', 'view_project'])) {
            return true;
        }

        $member = $this->getActiveMember($task->project, $user->id);
        return (bool)($member && $member->can_view);
    }

    private function canEditTaskProject(?User $user, Project $project): bool
    {
        if (!$user) return false;

        if ($this->hasAnyPermission($user, [
            'tasks.create', 'task.create', 'create_task',
            'tasks.update', 'task.update', 'edit_task',
            'projects.update', 'project.update', 'project.edit', 'edit_project'
        ])) {
            return true;
        }

        $member = $this->getActiveMember($project, $user->id);
        return (bool)($member && $member->can_edit);
    }

    private function canEditTask(?User $user, Task $task): bool
    {
        if (!$user) return false;

        if ($this->hasAnyPermission($user, [
            'tasks.update', 'task.update', 'edit_task',
            'projects.update', 'project.update', 'project.edit', 'edit_project'
        ])) {
            return true;
        }

        $member = $this->getActiveMember($task->project, $user->id);
        return (bool)($member && $member->can_edit);
    }

    private function canDeleteTask(?User $user, Task $task): bool
    {
        if (!$user) return false;

        if ($this->hasAnyPermission($user, [
            'tasks.delete', 'task.delete', 'delete_task',
            'projects.delete', 'project.delete', 'delete_project'
        ])) {
            return true;
        }

        $member = $this->getActiveMember($task->project, $user->id);
        return (bool)($member && $member->can_delete);
    }

    private function notifyTaskAssigned(Task $task, ?User $actor, string $type): void
    {
        if (!$task->assignedTo || (int) $task->assigned_to === (int) $actor?->id) {
            return;
        }

        $actorName = $actor?->full_name ?? 'System';
        $projectTitle = $task->project?->title ?? 'the project';

        try {
            app(NotificationService::class)->notifyUser(
                $task->assignedTo,
                $type,
                ($type === 'task_reassigned' ? 'Task reassigned: ' : 'Task assigned: ') . $task->title,
                "{$actorName} assigned you to {$task->title} in {$projectTitle}.",
                $task,
                'task_assigned',
                [
                    'user_name' => $task->assignedTo->full_name,
                    'task_title' => $task->title,
                    'project_name' => $task->project?->title ?? 'Project',
                    'due_date' => $task->due_date?->toFormattedDateString() ?? 'No due date',
                    'priority' => ucfirst((string) ($task->priority ?? 'normal')),
                    'task_description' => $task->description ?? 'No description provided.',
                ]
            );
        } catch (\Throwable $notificationException) {
            \Log::warning('Task assignment notification failed.', [
                'task_id' => $task->id,
                'error' => $notificationException->getMessage(),
            ]);
        }
    }

    private function notifyTaskUpdated(Task $task, ?User $actor): void
    {
        if (!$task->assignedTo) {
            return;
        }

        $actorName = $actor?->full_name ?? 'System';

        try {
            app(NotificationService::class)->notifyUser(
                $task->assignedTo,
                'task_updated',
                "Task updated: {$task->title}",
                "{$actorName} updated {$task->title}.",
                $task,
                null
            );
        } catch (\Throwable $notificationException) {
            \Log::warning('Task update notification failed.', [
                'task_id' => $task->id,
                'error' => $notificationException->getMessage(),
            ]);
        }
    }

    private function notifyTaskDeleted(Task $task, ?User $actor): void
    {
        if (!$task->assignedTo || (int) $task->assigned_to === (int) $actor?->id) {
            return;
        }

        $actorName = $actor?->full_name ?? 'System';

        try {
            app(NotificationService::class)->notifyUser(
                $task->assignedTo,
                'task_deleted',
                "Task removed: {$task->title}",
                "{$actorName} removed {$task->title}.",
                $task,
                null
            );
        } catch (\Throwable $notificationException) {
            \Log::warning('Task delete notification failed.', [
                'task_id' => $task->id,
                'error' => $notificationException->getMessage(),
            ]);
        }
    }

    private function notifyTaskCompleted(Task $task, ?User $actor): void
    {
        $task->loadMissing('project.creator');

        $recipients = collect([$task->assignedBy, $task->project?->creator])
            ->filter(fn ($user) => $user instanceof User && (int) $user->id !== (int) $actor?->id)
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $actorName = $actor?->full_name ?? 'System';

        try {
            app(NotificationService::class)->notifyUsers(
                $recipients,
                'task_completed',
                "Task completed: {$task->title}",
                "{$actorName} completed {$task->title}.",
                $task,
                null
            );
        } catch (\Throwable $notificationException) {
            \Log::warning('Task completion notification failed.', [
                'task_id' => $task->id,
                'error' => $notificationException->getMessage(),
            ]);
        }
    }

    private function notifyTaskProgressUpdated(Task $task, ?User $actor): void
    {
        if (!$task->assignedBy) {
            return;
        }

        $actorName = $actor?->full_name ?? 'System';

        try {
            app(NotificationService::class)->notifyUser(
                $task->assignedBy,
                'task_progress_updated',
                "Task progress updated: {$task->title}",
                "{$actorName} updated {$task->title} to {$task->progress_percentage}%.",
                $task,
                null
            );
        } catch (\Throwable $notificationException) {
            \Log::warning('Task progress notification failed.', [
                'task_id' => $task->id,
                'error' => $notificationException->getMessage(),
            ]);
        }
    }
}
