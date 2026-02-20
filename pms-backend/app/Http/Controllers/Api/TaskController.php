<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
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

        $query = Task::with(['project', 'assignedTo', 'assignedBy']);

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

        if ($request->has('overdue')) {
            $query->overdue();
        }

        $query->active(); // Only active tasks

        // Sorting
        $sortBy = $request->get('sort_by', 'due_date');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

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

        $task = Task::create(array_merge(
            $request->validated(),
            ['assigned_by' => auth()->id()]
        ));

        return new TaskResource($task->load(['project', 'assignedTo', 'assignedBy']));
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
            'parentTask', 'subtasks', 'dependencies', 'resources'
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

        $task->update($request->validated());

        return new TaskResource($task->fresh()->load(['project', 'assignedTo']));
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

        $task->update(['progress_percentage' => $request->progress_percentage]);

        // Auto-complete if 100%
        if ($request->progress_percentage == 100 && $task->status != 'completed') {
            $task->update([
                'status' => 'completed',
                'completion_date' => now(),
            ]);
        }

        return new TaskResource($task);
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
}
