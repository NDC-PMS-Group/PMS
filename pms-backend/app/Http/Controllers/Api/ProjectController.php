<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Requests\AddProjectMemberRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectStageHistory;
use App\Models\ProjectStatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $myProjectsOnly = $request->boolean('my_projects');
        $editableProjectsOnly = $request->boolean('editable_projects');

        $query = Project::with([
            'projectType', 'industry', 'sector', 'currentStage',
            'status', 'projectOfficer', 'workgroupHead', 'creator'
        ]);

        // Explicit scoped modes for task module usage.
        if ($editableProjectsOnly) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('members', function ($memberQuery) use ($user) {
                      $memberQuery
                          ->where('user_id', $user->id)
                          ->whereNull('removed_at')
                          ->where('can_edit', true);
                  });
            });
        } elseif ($myProjectsOnly) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('members', function ($memberQuery) use ($user) {
                      $memberQuery
                          ->where('user_id', $user->id)
                          ->whereNull('removed_at')
                          ->where('can_view', true);
                  });
            });
        } elseif (!$this->hasAnyPermission($user, ['projects.view', 'project.view', 'view_project'])) {
            // If user has no global project view permission, only show projects they can view as member.
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('members', function ($memberQuery) use ($user) {
                      $memberQuery
                          ->where('user_id', $user->id)
                          ->whereNull('removed_at')
                          ->where('can_view', true);
                  });
            });
        }

        // Filters
        if ($request->has('stage_id')) {
            $query->where('current_stage_id', $request->stage_id);
        }

        if ($request->has('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->has('project_type_id')) {
            $query->where('project_type_id', $request->project_type_id);
        }

        if ($request->has('industry_id')) {
            $query->where('industry_id', $request->industry_id);
        }

        if ($request->has('is_svf')) {
            $query->where('is_svf', $request->boolean('is_svf'));
        }

        if ($request->has('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        } else {
            $query->active(); // Default: only active projects
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('project_code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $projects = $query->paginate($perPage);

        return ProjectResource::collection($projects);
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request)
    {
        if (!$this->canCreateProject($request->user())) {
            return response()->json(['message' => 'Unauthorized to create projects'], 403);
        }

        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            DB::beginTransaction();
            try {
                // Generate project code
                $projectCode = $this->generateProjectCode($request->project_type_id);

                $project = Project::create(array_merge(
                    $request->validated(),
                    [
                        'project_code' => $projectCode,
                        'created_by' => auth()->id(),
                    ]
                ));

                // Create stage history
                ProjectStageHistory::create([
                    'project_id' => $project->id,
                    'to_stage_id' => $project->current_stage_id,
                    'changed_by' => auth()->id(),
                    'change_reason' => 'Project created',
                ]);

                // Create status history
                ProjectStatusHistory::create([
                    'project_id' => $project->id,
                    'to_status_id' => $project->status_id,
                    'changed_by' => auth()->id(),
                    'change_reason' => 'Project created',
                ]);

                // Ensure project creator is a member with full management capabilities.
                $project->members()->updateOrCreate(
                    ['user_id' => auth()->id()],
                    [
                        'role_id' => $request->user()?->default_role_id,
                        'assignment_type' => 'owner',
                        'can_view' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_approve' => true,
                        'can_manage_members' => true,
                        'assigned_by' => auth()->id(),
                        'removed_at' => null,
                    ]
                );

                // Initialize SOI approval routing:
                // Proponent -> Project Officer -> Workgroup Head -> ManCom -> Board.
                ApprovalController::createInitialApprovalForProject(
                    (int) $project->id,
                    $project->project_type_id ? (int) $project->project_type_id : null,
                    (int) auth()->id()
                );

                DB::commit();

                return new ProjectResource($project->load([
                    'projectType', 'industry', 'sector', 'currentStage',
                    'status', 'projectOfficer', 'workgroupHead'
                ]));
            } catch (QueryException $e) {
                DB::rollBack();

                if ($this->isDuplicateProjectCodeError($e) && $attempt < $maxAttempts) {
                    continue;
                }

                if ($this->isDuplicateProjectCodeError($e)) {
                    return response()->json([
                        'message' => 'Unable to generate a unique project code. Please retry.'
                    ], 422);
                }

                return response()->json(['message' => 'Error creating project', 'error' => $e->getMessage()], 500);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['message' => 'Error creating project', 'error' => $e->getMessage()], 500);
            }
        }

        return response()->json(['message' => 'Error creating project'], 500);
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project)
    {
        if (!$this->canViewProject(auth()->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to view this project'], 403);
        }

        $project->load([
            'projectType', 'industry', 'sector', 'investmentType', 'fundingSource',
            'currentStage', 'status', 'projectOfficer', 'workgroupHead', 'creator',
            'members.user', 'members.role', 'members.assignedBy', 'tags', 'tasks', 'documents'
        ]);

        return new ProjectResource($project);
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, Project $project)
    {
        if (!$this->canEditProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to edit this project'], 403);
        }

        DB::beginTransaction();
        try {
            $oldStageId = $project->current_stage_id;
            $oldStatusId = $project->status_id;

            $project->update($request->validated());

            // Track stage change
            if ($request->has('current_stage_id') && $oldStageId != $request->current_stage_id) {
                ProjectStageHistory::create([
                    'project_id' => $project->id,
                    'from_stage_id' => $oldStageId,
                    'to_stage_id' => $request->current_stage_id,
                    'changed_by' => auth()->id(),
                    'change_reason' => $request->get('stage_change_reason'),
                ]);
            }

            // Track status change
            if ($request->has('status_id') && $oldStatusId != $request->status_id) {
                ProjectStatusHistory::create([
                    'project_id' => $project->id,
                    'from_status_id' => $oldStatusId,
                    'to_status_id' => $request->status_id,
                    'changed_by' => auth()->id(),
                    'change_reason' => $request->get('status_change_reason'),
                ]);
            }

            // If project reached completion/divestment, mark approved workflow as completed.
            if ($request->has('current_stage_id')) {
                $project->loadMissing('currentStage', 'approvals');
                $stageName = $project->currentStage?->name;
                if (in_array($stageName, ['Completion', 'Divestment'], true)) {
                    $approval = $project->approvals()
                        ->whereIn('overall_status', ['approved', 'approved_with_conditions'])
                        ->latest('id')
                        ->first();

                    if ($approval) {
                        $approval->update([
                            'overall_status' => 'completed',
                            'completed_at' => now(),
                        ]);
                    }
                }
            }

            DB::commit();

            return new ProjectResource($project->fresh()->load([
                'projectType', 'industry', 'sector', 'currentStage', 'status'
            ]));

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error updating project', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified project (soft delete).
     */
    public function destroy(Project $project)
    {
        if (!$this->canDeleteProject(auth()->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to delete this project'], 403);
        }

        $project->update(['is_deleted' => true]);
        $project->delete(); // Soft delete

        return response()->json(['message' => 'Project deleted successfully'], 200);
    }

    /**
     * Add or update a member in the project.
     */
    public function addMember(AddProjectMemberRequest $request, Project $project)
    {
        if (!$this->canManageMembers($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to manage project members'], 403);
        }

        $payload = array_merge([
            'assignment_type' => 'member',
            'can_view' => true,
            'can_edit' => false,
            'can_delete' => false,
            'can_approve' => false,
            'can_manage_members' => false,
        ], $request->validated(), [
            'assigned_by' => auth()->id(),
            'removed_at' => null,
        ]);

        $member = $project->members()->where('user_id', $payload['user_id'])->first();

        if ($member) {
            $member->update($payload);
        } else {
            $member = $project->members()->create($payload);
        }

        return response()->json([
            'message' => 'Member saved successfully',
            'member' => $member->load('user', 'role', 'assignedBy')
        ], 201);
    }

    /**
     * Remove a member from the project.
     */
    public function removeMember(Project $project, $memberId)
    {
        if (!$this->canManageMembers(auth()->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to manage project members'], 403);
        }

        $member = $project->members()->findOrFail($memberId);
        $member->update(['removed_at' => now()]);

        return response()->json(['message' => 'Member removed successfully'], 200);
    }

    /**
     * Get project timeline (stage & status history).
     */
    public function timeline(Project $project)
    {
        if (!$this->canViewProject(auth()->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to view this project timeline'], 403);
        }

        $stageHistory = $project->stageHistory()
            ->with(['fromStage', 'toStage', 'changedBy'])
            ->orderBy('changed_at', 'desc')
            ->get();

        $statusHistory = $project->statusHistory()
            ->with(['fromStatus', 'toStatus', 'changedBy'])
            ->orderBy('changed_at', 'desc')
            ->get();

        return response()->json([
            'stage_history' => $stageHistory,
            'status_history' => $statusHistory,
        ]);
    }

    /**
     * Archive/unarchive project.
     */
    public function archive(Project $project)
    {
        if (!$this->canEditProject(auth()->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to archive this project'], 403);
        }

        $project->update(['is_archived' => !$project->is_archived]);

        return response()->json([
            'message' => $project->is_archived ? 'Project archived' : 'Project unarchived',
            'project' => new ProjectResource($project)
        ]);
    }

    /**
     * Generate unique project code.
     */
    private function generateProjectCode($projectTypeId = null)
    {
        $prefix = 'BDG'; // Default prefix
        $year = date('Y');

        // Include soft-deleted projects to avoid reusing codes from archived/deleted records.
        $maxNumber = (int) Project::withTrashed()
            ->where('project_code', 'like', "{$prefix}-{$year}-%")
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(project_code, '-', -1) AS UNSIGNED)) AS max_number")
            ->value('max_number');

        $nextNumber = $maxNumber + 1;
        $newNumber = str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$newNumber}";
    }

    private function isDuplicateProjectCodeError(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            && str_contains($e->getMessage(), 'projects_project_code_unique');
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

    private function canCreateProject(?User $user): bool
    {
        return $this->hasAnyPermission($user, ['projects.create', 'project.create', 'create_project']);
    }

    private function canViewProject(?User $user, Project $project): bool
    {
        if (!$user) return false;

        if ($this->hasAnyPermission($user, ['projects.view', 'project.view', 'view_project'])) {
            return true;
        }

        $member = $this->getActiveMember($project, $user->id);
        return (bool)($member && $member->can_view);
    }

    private function canEditProject(?User $user, Project $project): bool
    {
        if (!$user) return false;

        if ($this->hasAnyPermission($user, ['projects.update', 'projects.edit', 'project.update', 'project.edit', 'edit_project'])) {
            return true;
        }

        $member = $this->getActiveMember($project, $user->id);
        return (bool)($member && $member->can_edit);
    }

    private function canDeleteProject(?User $user, Project $project): bool
    {
        if (!$user) return false;

        if ($this->hasAnyPermission($user, ['projects.delete', 'project.delete', 'delete_project'])) {
            return true;
        }

        $member = $this->getActiveMember($project, $user->id);
        return (bool)($member && $member->can_delete);
    }

    private function canManageMembers(?User $user, Project $project): bool
    {
        if (!$user) return false;

        if ((int)$project->created_by === (int)$user->id) {
            return true;
        }

        if ($this->hasAnyPermission($user, [
            'projects.members.manage',
            'project_members.manage',
            'project_member.manage',
            'manage_members',
        ])) {
            return true;
        }

        // Fallback: users who can edit project are allowed to manage project members.
        if ($this->canEditProject($user, $project)) {
            return true;
        }

        $member = $this->getActiveMember($project, $user->id);
        return (bool)($member && $member->can_manage_members);
    }
}
