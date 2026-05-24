<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Requests\AddProjectMemberRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectRequirement;
use App\Models\ProjectStageHistory;
use App\Models\ProjectStatus;
use App\Models\ProjectStatusHistory;
use App\Models\Task;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            'status', 'projectOfficer', 'workgroupHead', 'creator',
            'members' => fn ($memberQuery) => $memberQuery->active()->with(['user', 'role']),
            'requirements',
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

                $projectPayload = $this->prepareProjectPayload($request);

                $project = Project::create(array_merge(
                    $projectPayload,
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

                $this->seedDefaultRequirements($project);
                $this->seedLifecycleTasks($project, (int) auth()->id());

                DB::commit();

                return new ProjectResource($project->load([
                    'projectType', 'industry', 'sector', 'currentStage',
                    'status', 'projectOfficer', 'workgroupHead', 'creator',
                    'members.user', 'members.role', 'requirements'
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
            'members.user', 'members.role', 'members.assignedBy', 'tags',
            'tasks' => fn ($query) => $query->active()->with(['assignedTo', 'assignedBy', 'subtasks.assignedTo']),
            'documents' => fn ($query) => $query->active()->with(['uploadedBy', 'task']),
            'requirements.document.uploadedBy', 'requirements.receivedBy',
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

        if ($this->isProjectChangeLocked($request->user(), $project)) {
            return response()->json([
                'message' => 'Project details are locked after submission or approval. Please request a revision through the approval workflow.',
            ], 423);
        }

        DB::beginTransaction();
        try {
            $oldStageId = $project->current_stage_id;
            $oldStatusId = $project->status_id;
            $oldStatusName = ProjectStatus::find($oldStatusId)?->name ?? 'Existing details';

            $project->update($request->validated());
            $projectChanged = $project->wasChanged();

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

            $freshProject = $project->fresh()->load([
                'projectType', 'industry', 'sector', 'currentStage', 'status'
            ]);

            if ($projectChanged) {
                try {
                    $notificationService = app(NotificationService::class);
                    $notificationService->notifyUsers(
                        $notificationService->projectStakeholders($freshProject),
                        'project_updated',
                        "Project updated: {$freshProject->project_code}",
                        "{$freshProject->title} details were updated.",
                        $freshProject,
                        'project_status_change',
                        [
                            'project_title' => $freshProject->title,
                            'old_status' => $oldStatusName,
                            'new_status' => $freshProject->status?->name ?? 'Updated details',
                            'changed_by' => $request->user()?->full_name ?? 'System',
                            'reason' => $request->get('status_change_reason')
                                ?? $request->get('stage_change_reason')
                                ?? 'Project details updated.',
                        ]
                    );
                } catch (\Throwable $notificationException) {
                    \Log::warning('Project update notification failed.', [
                        'project_id' => $freshProject->id,
                        'error' => $notificationException->getMessage(),
                    ]);
                }
            }

            return new ProjectResource($freshProject);

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
            $notificationTitle = "Project access updated: {$project->project_code}";
            $notificationMessage = "Your project role or permissions were updated for {$project->title}.";
        } else {
            $member = $project->members()->create($payload);
            $notificationTitle = "Added to project: {$project->project_code}";
            $notificationMessage = "You were added to {$project->title}.";
        }

        try {
            $member->loadMissing('user');
            if ($member->user) {
                app(NotificationService::class)->notifyUser(
                    $member->user,
                    'project_member_added',
                    $notificationTitle,
                    $notificationMessage,
                    $project,
                    'project_status_change',
                    [
                        'user_name' => $member->user->full_name,
                        'project_title' => $project->title,
                        'old_status' => 'Project Membership',
                        'new_status' => 'Active Member',
                        'changed_by' => $request->user()?->full_name ?? 'System',
                        'reason' => $notificationMessage,
                    ]
                );
            }
        } catch (\Throwable $notificationException) {
            \Log::warning('Project member notification failed.', [
                'project_id' => $project->id,
                'member_id' => $member->id,
                'error' => $notificationException->getMessage(),
            ]);
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
        $member->loadMissing('user');
        $member->update(['removed_at' => now()]);

        try {
            if ($member->user) {
                app(NotificationService::class)->notifyUser(
                    $member->user,
                    'project_member_removed',
                    "Removed from project: {$project->project_code}",
                    "Your access to {$project->title} was removed.",
                    $project,
                    'project_status_change',
                    [
                        'user_name' => $member->user->full_name,
                        'project_title' => $project->title,
                        'old_status' => 'Active Member',
                        'new_status' => 'Removed',
                        'changed_by' => auth()->user()?->full_name ?? 'System',
                        'reason' => 'Project membership removed.',
                    ]
                );
            }
        } catch (\Throwable $notificationException) {
            \Log::warning('Project member removal notification failed.', [
                'project_id' => $project->id,
                'member_id' => $member->id,
                'error' => $notificationException->getMessage(),
            ]);
        }

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

        $approval = $project->approvals()->with(['workflow.steps.role', 'currentStep.role'])->latest('id')->first();
        $approvalHistory = [];
        if ($approval) {
            $approvalHistory = \App\Models\ApprovalStepRecord::with(['step.role', 'approver'])
                ->where('project_approval_id', $approval->id)
                ->orderBy('reviewed_at', 'desc')
                ->get();
        }

        return response()->json([
            'stage_history' => $stageHistory,
            'status_history' => $statusHistory,
            'current_approval' => $approval,
            'approval_history' => $approvalHistory,
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

        try {
            $notificationService = app(NotificationService::class);
            $notificationService->notifyUsers(
                $notificationService->projectStakeholders($project),
                $project->is_archived ? 'project_archived' : 'project_unarchived',
                ($project->is_archived ? 'Project archived: ' : 'Project unarchived: ') . $project->project_code,
                "{$project->title} was " . ($project->is_archived ? 'archived.' : 'restored from archive.'),
                $project,
                'project_status_change',
                [
                    'project_title' => $project->title,
                    'old_status' => $project->is_archived ? 'Active' : 'Archived',
                    'new_status' => $project->is_archived ? 'Archived' : 'Active',
                    'changed_by' => auth()->user()?->full_name ?? 'System',
                    'reason' => $project->is_archived ? 'Project archived.' : 'Project restored from archive.',
                ]
            );
        } catch (\Throwable $notificationException) {
            \Log::warning('Project archive notification failed.', [
                'project_id' => $project->id,
                'error' => $notificationException->getMessage(),
            ]);
        }

        return response()->json([
            'message' => $project->is_archived ? 'Project archived' : 'Project unarchived',
            'project' => new ProjectResource($project)
        ]);
    }

    public function updateRequirement(Request $request, Project $project, ProjectRequirement $requirement)
    {
        if ((int) $requirement->project_id !== (int) $project->id) {
            return response()->json(['message' => 'Requirement does not belong to this project'], 404);
        }

        if (!$this->canEditProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to update project requirements'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pending,requested,received,deferred,waived,approved,approved_with_conditions,disapproved,for_further_evaluation',
            'remarks' => 'nullable|string',
            'document_id' => 'nullable|exists:documents,id',
            'due_date' => 'nullable|date',
        ]);

        if (!empty($validated['document_id'])) {
            $documentBelongsToProject = $project->documents()
                ->whereKey($validated['document_id'])
                ->active()
                ->exists();

            if (!$documentBelongsToProject) {
                return response()->json(['message' => 'Attachment does not belong to this project'], 422);
            }
        }

        $requirement->update(array_merge($validated, [
            'received_at' => in_array($validated['status'], ['received', 'approved', 'approved_with_conditions'], true)
                ? now()
                : $requirement->received_at,
            'received_by' => in_array($validated['status'], ['received', 'approved', 'approved_with_conditions'], true)
                ? $request->user()?->id
                : $requirement->received_by,
        ]));

        return response()->json([
            'message' => 'Requirement updated',
            'requirement' => $requirement->fresh(['document.uploadedBy', 'receivedBy']),
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

    private function isSuperAdmin(?User $user): bool
    {
        return $user && ((int)$user->default_role_id === 1 || $user->hasRole('superadmin'));
    }

    private function isProjectChangeLocked(?User $user, Project $project): bool
    {
        if ($this->isSuperAdmin($user)) {
            return false;
        }

        $approval = $project->approvals()->latest('id')->first();
        if (!$approval) {
            return false;
        }

        return !in_array($approval->overall_status, ['pending', 'returned'], true);
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

    private function prepareProjectPayload(StoreProjectRequest $request): array
    {
        $payload = $request->validated();
        $user = $request->user();

        if ($user?->hasRole('Proponent')) {
            if (!in_array($payload['process_track'] ?? 'bdg_investment', ['bdg_investment', 'spg_jv'], true)) {
                throw ValidationException::withMessages([
                    'process_track' => ['External proponents may only submit investment or joint venture proposals.'],
                ]);
            }

            $payload['process_track'] = $payload['process_track'] ?? 'bdg_investment';
            $payload['is_svf'] = false;
            unset($payload['actual_cost']);

            $payload['proponent_name'] = $payload['proponent_name']
                ?? $user->organization_name
                ?? $user->full_name;
            $payload['proponent_email'] = $payload['proponent_email'] ?? $user->email;
            $payload['proponent_contact'] = $payload['proponent_contact'] ?? $user->phone_number;
            $payload['company_background'] = $payload['company_background']
                ?? trim(collect([
                    $user->organization_name,
                    $user->organization_type,
                    $user->organization_registration_no ? "Registration No. {$user->organization_registration_no}" : null,
                ])->filter()->join(' | '));
        }

        return $payload;
    }

    private function seedDefaultRequirements(Project $project): void
    {
        foreach ($this->defaultRequirementItems((string) ($project->process_track ?: 'bdg_investment'), (bool) $project->is_svf) as $item) {
            $project->requirements()->updateOrCreate(
                [
                    'group_name' => $item['group_name'],
                    'item_name' => $item['item_name'],
                ],
                $item
            );
        }
    }

    private function defaultRequirementItems(string $track, bool $isSvf): array
    {
        $items = [
            ['Eligibility Screening', 'Brochure / Pitch Deck', 'BDG Checklist / SOI', false],
            ['Eligibility Screening', 'Website or public company profile', 'BDG Checklist', false],
            ['Preliminary Requirements', 'Non-Disclosure Agreement (NDA)', 'SPG/BDG Templates', false],
            ['Preliminary Requirements', 'Letter of Intent addressed to NDC AGM', 'Proposal Requirements', false],
            ['Preliminary Requirements', 'Secretary Certificate or authority to submit proposal', 'Checklist', false],
            ['Preliminary Requirements', 'Data Privacy Consent Form', 'Checklist', false],
            ['Project Proposal', 'Project description, location, market condition, and reason', '1st Level Proposal', false],
            ['Project Proposal', 'Target beneficiaries and social/economic benefits', '1st Level Proposal', false],
            ['Project Proposal', 'Estimated project cost, projected revenue, and NDC participation', '1st Level Proposal', false],
            ['Project Proposal', 'Target implementation schedule', '1st Level Proposal', false],
            ['Project Proposal', 'Proponent background, shareholders, affiliates, and track record', '1st Level Proposal', false],
            ['Documentary Requirements', 'Feasibility Study / Pre-FS / Business Plan', '2nd Level Proposal', false],
            ['Documentary Requirements', 'Financial model or profitability analysis', '2nd Level Proposal', false],
            ['Documentary Requirements', 'Proof of site ownership, authority, or project location control', 'SOI', false],
            ['Documentary Requirements', 'SEC/DTI registration, Articles, and By-Laws', 'Checklist', false],
            ['Documentary Requirements', 'Audited financial statements for the last three years', 'Checklist', false],
            ['Documentary Requirements', 'BIR and tax clearance', 'SOI', false],
            ['Due Diligence', 'Third-party due diligence report', 'SOI', false],
            ['Due Diligence', 'Risk register and mitigation plan', 'Summary Sheet / Divestment SOI', false],
            ['Evaluation', 'Investment criteria assessment: at least three of pioneering, developmental, sustainable, inclusive, innovative', 'SPG Checklist', false],
            ['Evaluation', 'Investment Committee evaluation', 'BDG SOI', true],
            ['Management Committee Evaluation', 'ManCom paper / presentation material', 'SOI', false],
            ['Board Evaluation', 'Board paper, Board Resolution, or Secretary Certificate', 'SOI', false],
            ['Fund Deployment', 'Investment Agreement / Contract / JVA as applicable', 'Checklist', false],
            ['Fund Deployment', 'Receipt issued by investee company or release evidence', 'Checklist', false],
            ['Monitoring', 'Project Summary Sheet with milestones, issues, next steps, covenants, and updates', 'Implementation SOI', false],
        ];

        if ($track === 'spg_jv') {
            $items[] = ['JV Requirements', 'NEDA endorsement / ICC requirements as applicable', 'SPG JV SOI', false];
            $items[] = ['JV Requirements', 'JV Selection Committee documents and Notice of Award', 'SPG JV SOI', false];
            $items[] = ['JV Requirements', 'Joint Venture Agreement signed by parties', 'SPG JV SOI', false];
        }

        if ($track === 'spg_ndc_own') {
            $items[] = ['NDC-Owned Project', 'ManCom approval to proceed with study or consultancy', 'NDC-on-Own SOI', false];
            $items[] = ['NDC-Owned Project', 'Procurement / bidding documents for study, DED, or construction', 'NDC-on-Own SOI', false];
        }

        if ($track === 'divestment') {
            $items[] = ['Divestment', 'Legal due diligence report / legal memo', 'Divestment SOI', false];
            $items[] = ['Divestment', 'Financial due diligence and updated financial statements', 'Divestment SOI', false];
            $items[] = ['Divestment', 'Transfer documents, collection evidence, and receipts', 'Divestment SOI', false];
        }

        return collect($items)
            ->filter(fn ($item) => !$item[3] || $isSvf)
            ->values()
            ->map(fn ($item, $index) => [
                'group_name' => $item[0],
                'item_name' => $item[1],
                'source_document' => $item[2],
                'track' => $track,
                'is_required' => true,
                'is_applicable' => true,
                'svf_only' => (bool) $item[3],
                'status' => 'pending',
                'sort_order' => ($index + 1) * 10,
            ])
            ->all();
    }

    private function seedLifecycleTasks(Project $project, int $createdBy): void
    {
        if ($project->tasks()->exists()) {
            return;
        }

        $ownerId = $project->project_officer_id ?: $createdBy;
        $proponentId = $project->created_by ?: $createdBy;
        $workgroupHeadId = $project->workgroup_head_id ?: $ownerId;
        $today = now()->startOfDay();
        $tasks = [
            ['Pre-screening and KYC', 'Confirm proponent identity, mandate fit, and priority alignment.', 'intake', $ownerId, 7, 'critical', true],
            ['Receive LOI and project concept', 'Record LOI, project concept, pitch deck, and basic proposal information.', 'requirements', $proponentId, 10, 'critical', true],
            ['Issue response and requirements checklist', 'Send Citizen Charter response and list of documentary requirements.', 'requirements', $ownerId, 15, 'urgent', true],
            ['Validate complete documentary requirements', 'Check proposal, corporate, legal, tax, and financial documents.', 'compliance', $ownerId, 30, 'urgent', false],
            ['Prepare due diligence and evaluation report', 'Triangulate documents, financial model, risk register, and feasibility evidence.', 'due_diligence', $ownerId, 45, 'high', true],
            ['Prepare ManCom decision paper', 'Summarize options, recommendation, risks, and required management action.', 'approval', $workgroupHeadId, 60, 'high', true],
            ['Prepare Board approval package', 'Prepare Board paper, resolution, secretary certificate, and condition tracker.', 'approval', $workgroupHeadId, 75, 'high', true],
            ['Agreement signing and fund release readiness', 'Coordinate legal, finance, OGCC/compliance items, signatures, and release evidence.', 'fund_release', $ownerId, 90, 'medium', true],
            ['Monthly implementation monitoring summary', 'Maintain summary sheet, milestone updates, covenants, issues, and next steps.', 'monitoring', $ownerId, 120, 'medium', false],
        ];

        if ($project->is_svf) {
            array_splice($tasks, 5, 0, [[
                'Investment Committee evaluation',
                'Prepare SVF IC materials and capture IC action before ManCom.',
                'approval',
                $workgroupHeadId,
                52,
                'urgent',
                true,
            ]]);
        }

        foreach ($tasks as [$title, $description, $type, $assignedTo, $days, $priority, $isMilestone]) {
            Task::create([
                'project_id' => $project->id,
                'title' => $title,
                'description' => $description,
                'task_type' => $type,
                'assigned_to' => $assignedTo,
                'assigned_by' => $createdBy,
                'start_date' => $today->copy()->addDays(max(0, $days - 7))->toDateString(),
                'due_date' => $today->copy()->addDays($days)->toDateString(),
                'status' => 'pending',
                'progress_percentage' => 0,
                'priority' => $priority,
                'is_milestone' => $isMilestone,
                'is_deleted' => false,
            ]);
        }
    }
}
