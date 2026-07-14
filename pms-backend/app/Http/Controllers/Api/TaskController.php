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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks.
     */
    public function index(Request $request)
    {
        $visibleQuery = $this->visibleTaskQuery($request);
        $filteredQuery = $this->applyTaskFilters(clone $visibleQuery, $request);
        $summary = $this->taskSummary(clone $filteredQuery);
        $facets = $this->taskFacets($visibleQuery, $request);
        $permissions = $this->workspacePermissions($request);

        if ($request->get('view') === 'board') {
            return response()->json([
                'data' => [],
                'board' => $this->boardLanes($filteredQuery, $request),
                'summary' => $summary,
                'facets' => $facets,
                'permissions' => $permissions,
            ]);
        }

        $perPage = min(max($request->integer('per_page', 25), 1), 100);
        $tasks = $this->applyTaskSorting($filteredQuery, $request)->paginate($perPage);

        return TaskResource::collection($tasks)->additional([
            'summary' => $summary,
            'facets' => $facets,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created task.
     */
    public function store(StoreTaskRequest $request)
    {
        $project = Project::findOrFail($request->validated('project_id'));

        if (! $this->canEditTaskProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to create task in this project'], 403);
        }

        if ($project->lifecycle_phase !== 'implementation_monitoring') {
            return response()->json([
                'message' => 'Implementation tasks can only be created after the project starts implementation.',
            ], 422);
        }

        $payload = $request->validated();
        $payload['status'] = $payload['status'] ?? 'pending';
        $payload['progress_percentage'] = $payload['progress_percentage'] ?? 0;
        $payload['task_scope'] = 'implementation';
        $payload['task_type'] = $payload['task_type'] ?? 'implementation';
        $payload['soi_section'] = null;
        $payload['workstream'] = $payload['workstream'] ?? 'General Delivery';

        if (! empty($payload['parent_task_id'])) {
            $parent = Task::query()->find($payload['parent_task_id']);
            if (! $parent
                || (int) $parent->project_id !== (int) $project->id
                || $parent->task_scope !== 'implementation'
                || $parent->archived_at) {
                return response()->json(['message' => 'The checklist parent must be an active implementation task in the same project.'], 422);
            }
        }

        if (($payload['status'] ?? null) === 'completed') {
            $payload['completion_date'] = now()->toDateString();
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
        if (! $this->canViewTask(auth()->user(), $task)) {
            return response()->json(['message' => 'Unauthorized to view this task'], 403);
        }

        $task->load([
            'project', 'assignedTo', 'assignedBy',
            'parentTask', 'subtasks.assignedTo', 'subtasks.statusHistory', 'dependencies', 'resources', 'statusHistory',
        ]);

        return new TaskResource($task);
    }

    /**
     * Update the specified task.
     */
    public function update(UpdateTaskRequest $request, Task $task)
    {
        if (! $this->canEditTask(auth()->user(), $task)) {
            return response()->json(['message' => 'Unauthorized to update this task'], 403);
        }

        if ($task->task_scope !== 'implementation' || $task->project->lifecycle_phase !== 'implementation_monitoring') {
            return response()->json(['message' => 'Only active implementation tasks can be updated.'], 422);
        }

        $oldAssignedTo = $task->assigned_to;
        $oldStatus = $task->status;
        $oldDueDate = $task->due_date?->toDateString();
        $oldPriority = $task->priority;
        $oldProgress = $task->progress_percentage;

        $payload = $request->validated();
        $payload['soi_section'] = null;
        $payload['task_scope'] = 'implementation';

        $hasChecklistItems = $task->subtasks()->active()->implementation()->exists();
        if ($hasChecklistItems
            && array_key_exists('status', $payload)
            && $payload['status'] !== $task->status) {
            return response()->json(['message' => 'Parent task status is calculated from its checklist items.'], 422);
        }

        if (($payload['status'] ?? null) === 'completed') {
            $incompleteChildren = $task->subtasks()->active()->implementation()->where('status', '!=', 'completed')->exists();
            if ($incompleteChildren) {
                return response()->json(['message' => 'Complete every checklist item before completing the parent task.'], 422);
            }
        }

        if (($payload['status'] ?? null) === 'completed' && empty($payload['completion_date']) && ! $task->completion_date) {
            $payload['completion_date'] = now()->toDateString();
            $payload['progress_percentage'] = $payload['progress_percentage'] ?? 100;
        } elseif (
            array_key_exists('status', $payload)
            && $payload['status'] !== 'completed'
            && ! array_key_exists('completion_date', $payload)
        ) {
            $payload['completion_date'] = null;
        }

        $task->update($payload);

        $freshTask = $task->fresh(['project', 'assignedTo', 'assignedBy', 'statusHistory']);
        if ($freshTask->parent_task_id) {
            $this->syncParentCompletion($freshTask->parentTask, $request->user());
        }

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
        if (! $this->canDeleteTask(auth()->user(), $task)) {
            return response()->json(['message' => 'Unauthorized to delete this task'], 403);
        }

        $task->update(['is_deleted' => true]);

        if (! $task->parent_task_id) {
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
        if (! $this->canEditTask(auth()->user(), $task)) {
            return response()->json(['message' => 'Unauthorized to update task progress'], 403);
        }

        if ($task->task_scope !== 'implementation'
            || $task->archived_at
            || $task->project->lifecycle_phase !== 'implementation_monitoring') {
            return response()->json(['message' => 'Only active implementation tasks can be updated.'], 422);
        }

        $request->validate([
            'progress_percentage' => 'required|integer|min:0|max:100',
        ]);

        if ($task->subtasks()->active()->implementation()->exists()) {
            return response()->json(['message' => 'Parent task progress is calculated from its checklist items.'], 422);
        }

        $oldStatus = $task->status;
        $oldProgress = $task->progress_percentage;

        $task->update(['progress_percentage' => $request->progress_percentage]);

        // Auto-complete if 100%
        if ($request->progress_percentage == 100 && $task->status != 'completed') {
            $task->update([
                'status' => 'completed',
                'completion_date' => now()->toDateString(),
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

        if ($task->parent_task_id) {
            $this->syncParentCompletion($task->parentTask, $request->user());
        }

        return new TaskResource($task->fresh(['project', 'assignedTo', 'assignedBy', 'statusHistory']));
    }

    public function updateCompletion(Request $request, Task $task)
    {
        if (! $this->canEditTask($request->user(), $task)) {
            return response()->json(['message' => 'Unauthorized to update task completion'], 403);
        }

        if ($task->task_scope !== 'implementation'
            || $task->archived_at
            || $task->project->lifecycle_phase !== 'implementation_monitoring') {
            return response()->json(['message' => 'Only active implementation tasks can be completed.'], 422);
        }

        $validated = $request->validate(['completed' => 'required|boolean']);
        $completed = (bool) $validated['completed'];
        $children = $task->subtasks()->active()->implementation()->get();

        if (! $completed && $children->isNotEmpty()) {
            return response()->json([
                'message' => 'Reopen a checklist item to reopen its parent task.',
            ], 422);
        }

        if ($completed && $children->isNotEmpty() && $children->contains(fn (Task $child) => $child->status !== 'completed')) {
            return response()->json([
                'message' => 'Complete every checklist item before completing the parent task.',
            ], 422);
        }

        $oldStatus = $task->status;
        $oldProgress = (int) $task->progress_percentage;
        if ($completed) {
            $newStatus = 'completed';
            $newProgress = 100;
            $completionDate = today();
        } else {
            $completionHistory = $task->statusHistory()
                ->where('to_status', 'completed')
                ->latest('changed_at')
                ->latest('id')
                ->first();
            $newStatus = in_array($completionHistory?->from_status, ['pending', 'in_progress'], true)
                ? $completionHistory->from_status
                : 'in_progress';
            $newProgress = min(99, max(0, (int) ($completionHistory?->from_progress ?? 0)));
            $completionDate = null;
        }

        if ($oldStatus !== $newStatus || $oldProgress !== $newProgress) {
            $task->update([
                'status' => $newStatus,
                'progress_percentage' => $newProgress,
                'completion_date' => $completionDate,
            ]);
            $this->recordTaskHistory(
                $task->fresh(),
                $oldStatus,
                $newStatus,
                $oldProgress,
                $newProgress,
                $request->user(),
                'status_changed',
                $completed ? 'Task completed from the implementation checklist.' : 'Task reopened from the implementation checklist.'
            );
        }

        if ($task->parent_task_id) {
            $this->syncParentCompletion($task->parentTask, $request->user());
        }

        $fresh = $task->fresh(['project', 'assignedTo', 'assignedBy', 'statusHistory']);
        if ($completed && $oldStatus !== 'completed') {
            $this->notifyTaskCompleted($fresh, $request->user());
        }

        return new TaskResource($fresh);
    }

    private function syncParentCompletion(?Task $parent, ?User $actor): void
    {
        if (! $parent || $parent->task_scope !== 'implementation' || $parent->archived_at) {
            return;
        }

        $children = $parent->subtasks()->active()->implementation()->get();
        if ($children->isEmpty()) {
            return;
        }

        $oldStatus = $parent->status;
        $oldProgress = (int) $parent->progress_percentage;
        $allCompleted = $children->every(fn (Task $child) => $child->status === 'completed');
        $newProgress = (int) round($children->avg('progress_percentage'));
        $newStatus = $allCompleted
            ? 'completed'
            : ($children->contains(fn (Task $child) => in_array($child->status, ['in_progress', 'completed'], true)) ? 'in_progress' : 'pending');

        if ($oldStatus === $newStatus && $oldProgress === $newProgress) {
            return;
        }

        $parent->update([
            'status' => $newStatus,
            'progress_percentage' => $newProgress,
            'completion_date' => $allCompleted ? today() : null,
        ]);
        $this->recordTaskHistory(
            $parent->fresh(),
            $oldStatus,
            $newStatus,
            $oldProgress,
            $newProgress,
            $actor,
            $oldStatus === $newStatus ? 'progress_updated' : 'status_changed',
            'Parent task synchronized from checklist completion.'
        );
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
            default => 'Task status changed from '.($fromStatus ?: 'none')." to {$toStatus}.",
        };
    }

    private function visibleTaskQuery(Request $request): Builder
    {
        $user = $request->user();
        $query = Task::query()
            ->with(['project', 'assignedTo', 'assignedBy', 'statusHistory', 'subtasks.assignedTo', 'subtasks.statusHistory'])
            ->active()
            ->implementation()
            ->whereHas('project', fn ($projectQuery) => $projectQuery->accessibleTo(
                $user,
                ['tasks.view', 'task.view', 'view_task', 'projects.view'],
                $request->boolean('my_projects')
            )->where('lifecycle_phase', 'implementation_monitoring'));

        return $query;
    }

    private function applyTaskFilters(Builder $query, Request $request, array $except = []): Builder
    {
        if (! in_array('project_id', $except, true) && $request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if (! in_array('search', $except, true) && $request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $searchQuery) use ($search) {
                $searchQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('project', function ($projectQuery) use ($search) {
                        $projectQuery
                            ->where('project_code', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%");
                    });
            });
        }

        $directFilters = [
            'assigned_to' => 'assigned_to',
            'status' => 'status',
            'priority' => 'priority',
            'workstream' => 'workstream',
        ];
        foreach ($directFilters as $requestKey => $column) {
            if (! in_array($requestKey, $except, true) && $request->filled($requestKey)) {
                $query->where($column, $request->input($requestKey));
            }
        }

        if (! in_array('process_track', $except, true) && $request->filled('process_track')) {
            $query->whereHas('project', fn ($projectQuery) => $projectQuery->where('process_track', $request->input('process_track')));
        }
        if (! in_array('is_milestone', $except, true) && $request->has('is_milestone')) {
            $query->where('is_milestone', $request->boolean('is_milestone'));
        }
        if ($request->boolean('top_level_only')) {
            $query->whereNull('parent_task_id');
        }
        if (! in_array('overdue', $except, true) && $request->boolean('overdue')) {
            $query->overdue();
        }
        if (! in_array('urgent', $except, true) && $request->boolean('urgent')) {
            $query->whereIn('priority', ['critical', 'urgent']);
        }

        return $query;
    }

    private function applyTaskSorting(Builder $query, Request $request): Builder
    {
        $sortBy = (string) $request->get('sort_by', 'smart_priority');
        $sortOrder = $request->get('sort_order') === 'desc' ? 'desc' : 'asc';

        if ($sortBy === 'smart_priority') {
            return $query
                ->orderByRaw('CASE WHEN parent_task_id IS NULL THEN 0 ELSE 1 END')
                ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'normal' THEN 4 WHEN 'low' THEN 5 ELSE 6 END")
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_date')
                ->orderBy('created_at');
        }

        $allowed = ['due_date', 'created_at', 'updated_at', 'title', 'status', 'priority', 'progress_percentage'];

        return $query->orderBy(in_array($sortBy, $allowed, true) ? $sortBy : 'due_date', $sortOrder);
    }

    private function taskSummary(Builder $query): array
    {
        $statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        $summary = ['total' => (clone $query)->count()];
        foreach ($statuses as $status) {
            $summary[$status] = (clone $query)->where('status', $status)->count();
        }

        $summary['overdue'] = (clone $query)->overdue()->count();
        $summary['urgent'] = (clone $query)->whereIn('priority', ['critical', 'urgent'])->count();

        return $summary;
    }

    private function taskFacets(Builder $visibleQuery, Request $request): array
    {
        $statusCounts = $this->facetCounts($this->applyTaskFilters(clone $visibleQuery, $request, ['status']), 'status');
        $priorityCounts = $this->facetCounts($this->applyTaskFilters(clone $visibleQuery, $request, ['priority']), 'priority');
        $sectionCounts = $this->facetCounts($this->applyTaskFilters(clone $visibleQuery, $request, ['workstream']), 'workstream');
        $projectCounts = $this->facetCounts($this->applyTaskFilters(clone $visibleQuery, $request, ['project_id']), 'project_id');
        $assigneeCounts = $this->facetCounts($this->applyTaskFilters(clone $visibleQuery, $request, ['assigned_to']), 'assigned_to');

        $projects = Project::query()
            ->whereIn('id', array_keys($projectCounts))
            ->orderBy('project_code')
            ->get(['id', 'project_code', 'title'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'label' => trim("{$project->project_code} - {$project->title}", ' -'),
                'count' => $projectCounts[$project->id] ?? 0,
            ])->values();

        $assignees = User::query()
            ->whereIn('id', array_filter(array_keys($assigneeCounts)))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'label' => $user->name ?: $user->email,
                'count' => $assigneeCounts[$user->id] ?? 0,
            ])->values();

        return [
            'statuses' => $this->namedFacet($statusCounts),
            'priorities' => $this->namedFacet($priorityCounts),
            'soi_sections' => $this->namedFacet($sectionCounts),
            'projects' => $projects,
            'assignees' => $assignees,
        ];
    }

    private function facetCounts(Builder $query, string $column): array
    {
        return $query
            ->reorder()
            ->selectRaw("{$column}, COUNT(*) as aggregate")
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function namedFacet(array $counts): array
    {
        return collect($counts)
            ->map(fn ($count, $value) => ['value' => (string) $value, 'count' => $count])
            ->values()
            ->all();
    }

    private function boardLanes(Builder $query, Request $request): array
    {
        $perPage = min(max($request->integer('lane_per_page', 10), 1), 50);
        $lanes = [];

        foreach (['pending', 'in_progress', 'completed', 'cancelled'] as $status) {
            $pageName = "lane_page_{$status}";
            $paginator = $this->applyTaskSorting(
                (clone $query)->where('status', $status),
                $request
            )->paginate($perPage, ['*'], $pageName);
            $lanes[$status] = $this->serializeLane($paginator, $request);
        }

        return $lanes;
    }

    private function serializeLane(LengthAwarePaginator $paginator, Request $request): array
    {
        return [
            'data' => TaskResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    private function workspacePermissions(Request $request): array
    {
        $user = $request->user();
        $project = $request->filled('project_id') ? Project::find($request->integer('project_id')) : null;
        $member = $project ? $this->getActiveMember($project, $user->id) : null;
        $isProjectEditor = (bool) ($project && $this->canEditTaskProject($user, $project));

        return [
            'can_view' => true,
            'can_create' => (bool) ($project && $project->lifecycle_phase === 'implementation_monitoring'
                && ($isProjectEditor || $this->hasAnyPermission($user, ['tasks.create', 'task.create', 'create_task']))),
            'can_update' => (bool) ((! $project || $project->lifecycle_phase === 'implementation_monitoring')
                && ($isProjectEditor || $this->hasAnyPermission($user, ['tasks.update', 'task.update', 'edit_task']))),
            'can_delete' => (bool) ($member?->can_delete) || $this->hasAnyPermission($user, ['tasks.delete', 'task.delete', 'delete_task']),
        ];
    }

    private function hasAnyPermission(?User $user, array $permissionNames): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) $user->default_role_id === 1 || $user->hasRole('superadmin')) {
            return true;
        }

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
        if (! $user) {
            return false;
        }

        if ($task->task_scope !== 'implementation' || $task->archived_at) {
            return false;
        }

        if ($this->isDraftOwnedByAnotherUser($user, $task->project)) {
            return false;
        }

        if ($this->hasGlobalTaskPermission($user, ['tasks.view', 'task.view', 'view_task', 'projects.view', 'project.view', 'view_project'])) {
            return true;
        }

        $member = $this->getActiveMember($task->project, $user->id);

        return (bool) ($member && $member->can_view);
    }

    private function canEditTaskProject(?User $user, Project $project): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isDraftOwnedByAnotherUser($user, $project)) {
            return false;
        }

        if ($this->hasGlobalTaskPermission($user, [
            'tasks.create', 'task.create', 'create_task',
            'tasks.update', 'task.update', 'edit_task',
            'projects.update', 'project.update', 'project.edit', 'edit_project',
        ])) {
            return true;
        }

        $member = $this->getActiveMember($project, $user->id);

        return (bool) ($member && $member->can_edit);
    }

    private function canEditTask(?User $user, Task $task): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isDraftOwnedByAnotherUser($user, $task->project)) {
            return false;
        }

        if ($this->hasGlobalTaskPermission($user, [
            'tasks.update', 'task.update', 'edit_task',
            'projects.update', 'project.update', 'project.edit', 'edit_project',
        ])) {
            return true;
        }

        $member = $this->getActiveMember($task->project, $user->id);

        return (bool) ($member && $member->can_edit);
    }

    private function canDeleteTask(?User $user, Task $task): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isDraftOwnedByAnotherUser($user, $task->project)) {
            return false;
        }

        if ($this->hasGlobalTaskPermission($user, [
            'tasks.delete', 'task.delete', 'delete_task',
            'projects.delete', 'project.delete', 'delete_project',
        ])) {
            return true;
        }

        $member = $this->getActiveMember($task->project, $user->id);

        return (bool) ($member && $member->can_delete);
    }

    private function isDraftOwnedByAnotherUser(User $user, Project $project): bool
    {
        $project->loadMissing('status');

        return strcasecmp((string) $project->status?->name, 'Draft') === 0
            && (int) $project->created_by !== (int) $user->id;
    }

    private function isExternalProponent(?User $user): bool
    {
        return $user && ((int) $user->default_role_id === 7 || $user->hasRole('Proponent'));
    }

    private function hasGlobalTaskPermission(?User $user, array $permissionNames): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) $user->default_role_id === 1 || $user->hasRole('superadmin')) {
            return true;
        }

        if ($this->isExternalProponent($user)) {
            return false;
        }

        return $this->hasAnyPermission($user, $permissionNames);
    }

    private function notifyTaskAssigned(Task $task, ?User $actor, string $type): void
    {
        if (! $task->assignedTo || (int) $task->assigned_to === (int) $actor?->id) {
            return;
        }

        $actorName = $actor?->full_name ?? 'System';
        $projectTitle = $task->project?->title ?? 'the project';

        try {
            app(NotificationService::class)->notifyUser(
                $task->assignedTo,
                $type,
                ($type === 'task_reassigned' ? 'Task reassigned: ' : 'Task assigned: ').$task->title,
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
        if (! $task->assignedTo) {
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
        if (! $task->assignedTo || (int) $task->assigned_to === (int) $actor?->id) {
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
        if (! $task->assignedBy) {
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
