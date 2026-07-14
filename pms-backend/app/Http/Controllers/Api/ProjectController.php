<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Requests\AddProjectMemberRequest;
use App\Http\Resources\ProjectImageResource;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ApprovalWorkflow;
use App\Models\ProjectApproval;
use App\Models\Document;
use App\Models\ProjectImage;
use App\Models\ProjectMember;
use App\Models\ProjectRequirement;
use App\Models\DefaultRequirement;
use App\Models\ProjectStage;
use App\Models\ProjectStageHistory;
use App\Models\ProjectStatus;
use App\Models\ProjectStatusHistory;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\ImplementationAlreadyStartedException;
use App\Services\ImplementationLifecycleService;
use App\Services\ImplementationNotReadyException;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function workflowCatalog()
    {
        return response()->json([
            'data' => [
                'origins' => [
                    ['key' => 'bdg_investment', 'label' => 'External Investment Proposal (BDG)', 'workflow' => 'NDC BDG Investment Approval', 'audiences' => ['internal', 'proponent'], 'variants' => [['key' => 'svf', 'label' => 'Small Value Fund', 'workflow' => 'NDC SVF Investment Approval']]],
                    ['key' => 'spg_jv', 'label' => 'Joint Venture Proposal (SPG)', 'workflow' => 'SPG Joint Venture Project Approval', 'audiences' => ['internal', 'proponent'], 'variants' => []],
                    ['key' => 'spg_traditional', 'label' => 'Traditional Equity Funding (SPG)', 'workflow' => 'SPG Traditional Equity Funding Approval', 'audiences' => ['internal'], 'variants' => []],
                    ['key' => 'spg_ndc_own', 'label' => 'NDC-Owned Project (SPG)', 'workflow' => 'SPG NDC-Owned Project Approval', 'audiences' => ['internal'], 'variants' => []],
                ],
                'lifecycle_workflows' => [
                    ['key' => 'implementation_monitoring', 'label' => 'Implementation & Monitoring', 'entry_action' => 'start_implementation'],
                    ['key' => 'divestment', 'label' => 'Divestment / Exit', 'entry_action' => 'open_divestment_case'],
                ],
            ],
        ]);
    }

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
        ])->accessibleTo(
            $user,
            ['projects.view', 'project.view', 'view_project'],
            $myProjectsOnly
        );

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

        if ($request->has('sector_id')) {
            $query->where('sector_id', $request->sector_id);
        }

        if ($request->filled('process_track')) {
            $query->where('process_track', $request->get('process_track'));
        }

        if ($request->boolean('with_tasks')) {
            $query->with(['tasks' => fn ($taskQuery) => $taskQuery->active()->with(['assignedTo', 'subtasks.assignedTo'])]);
        }

        if ($request->filled('stage_name')) {
            $stageName = str_replace('_', ' ', strtolower((string) $request->get('stage_name')));
            $query->whereHas('currentStage', function ($stageQuery) use ($stageName) {
                $stageQuery->whereRaw('LOWER(name) LIKE ?', ["%{$stageName}%"]);
            });
        }

        if ($request->boolean('divestment_active')) {
            $query->where(function ($divestmentQuery) {
                $divestmentQuery
                    ->where('process_track', 'divestment')
                    ->orWhereHas('tasks', function ($taskQuery) {
                        $taskQuery
                            ->active()
                            ->where('soi_section', 'divestment');
                    });
            });
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

        $dateField = in_array($request->get('date_field'), [
            'created_at',
            'updated_at',
            'proposal_date',
            'start_date',
            'target_completion_date',
            'actual_completion_date',
        ], true) ? $request->get('date_field') : 'created_at';

        if ($request->filled('date_from')) {
            $query->whereDate($dateField, '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate($dateField, '<=', $request->date_to);
        }

        $this->applyReportPreset($query, (string) $request->get('report_preset', 'all'));

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = [
            'created_at',
            'updated_at',
            'title',
            'estimated_cost',
            'actual_cost',
            'target_completion_date',
        ];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }
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
                $projectPayload = $this->prepareProjectPayload($request);
                $projectCode = $this->generateProjectCode($projectPayload);

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

                $this->seedDefaultRequirements($project);
                if ($this->shouldStartApprovalOnCreate($project)) {
                    ApprovalController::createInitialApprovalForProject(
                        (int) $project->id,
                        $project->project_type_id ? (int) $project->project_type_id : null,
                        (int) auth()->id()
                    );
                }

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
            'currentStage', 'status', 'projectOfficer', 'workgroupHead', 'creator', 'proponentUser', 'monitoringActivatedBy', 'implementationStartedBy',
            'members.user', 'members.role', 'members.assignedBy', 'tags',
            'invitations.invitedBy', 'invitations.role',
            'tasks' => fn ($query) => $query->active()->implementation()->whereNull('parent_task_id')->with([
                'assignedTo',
                'assignedBy',
                'subtasks' => fn ($subtaskQuery) => $subtaskQuery->active()->implementation()->with('assignedTo'),
            ]),
            'documents' => fn ($query) => $query->active()->with(['uploadedBy', 'submittedBy', 'updateRequestedBy', 'task']),
            'fundReleases' => fn ($query) => $query->with(['requirement.document', 'task', 'document', 'fundingSource', 'preparedBy', 'reviewedBy', 'releasedBy']),
            'images.uploadedBy',
            'requirements.document.uploadedBy', 'requirements.document.submittedBy', 'requirements.document.updateRequestedBy', 'requirements.receivedBy',
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
                'message' => 'Project details are locked after submission or approval. Please request a revision through the SOI workflow.',
            ], 423);
        }

        DB::beginTransaction();
        try {
            $oldStageId = $project->current_stage_id;
            $oldStatusId = $project->status_id;
            $oldStatusName = ProjectStatus::find($oldStatusId)?->name ?? 'Existing details';

            if ($request->has('lifecycle_phase') && $request->input('lifecycle_phase') !== $project->lifecycle_phase) {
                $project->lifecycle_phase_started_at = now();
            }

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
                    $noticeData = [
                        'project_title' => $freshProject->title,
                        'old_status' => $oldStatusName,
                        'new_status' => $freshProject->status?->name ?? 'Updated details',
                        'changed_by' => $request->user()?->full_name ?? 'System',
                        'reason' => $request->get('status_change_reason')
                            ?? $request->get('stage_change_reason')
                            ?? 'Project details updated.',
                    ];

                    if ($this->isExternalProponent($request->user())) {
                        $notificationService->notifyUsers(
                            $notificationService->internalProjectStakeholders($freshProject, $request->user()),
                            'project_updated',
                            "Project updated by proponent: {$freshProject->project_code}",
                            "{$freshProject->title} details were updated by the proponent.",
                            $freshProject,
                            'project_status_change',
                            $noticeData
                        );
                    } else {
                        $notificationService->notifyProjectProponent(
                            $freshProject,
                            'project_updated',
                            "Project updated: {$freshProject->project_code}",
                            "{$freshProject->title} details were updated by NDC.",
                            'project_status_change',
                            $noticeData
                        );
                    }
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
     * Submit a saved proposal draft to the SOI approval route.
     */
    public function submitProposal(Request $request, Project $project)
    {
        if (!$this->canEditProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to submit this proposal'], 403);
        }

        if ($project->approvals()->exists()) {
            return response()->json([
                'message' => 'This proposal has already been submitted.',
                'project' => new ProjectResource($project->fresh()->load([
                    'projectType', 'industry', 'sector', 'currentStage', 'status',
                    'projectOfficer', 'workgroupHead', 'creator',
                    'members.user', 'members.role', 'requirements',
                ])),
            ], 409);
        }

        $missingRequirements = $this->missingInitialProposalRequirements($project);
        if ($missingRequirements->isNotEmpty()) {
            return response()->json([
                'message' => 'Complete the initial SOI proposal package before submitting.',
                'missing_requirements' => $missingRequirements->values(),
            ], 422);
        }

        $approval = ApprovalController::createInitialApprovalForProject(
            (int) $project->id,
            $project->project_type_id ? (int) $project->project_type_id : null,
            (int) $request->user()->id
        );

        if (!$approval) {
            return response()->json([
                'message' => 'The SOI approval workflow is not configured for this proposal.',
            ], 422);
        }

        $draftDocuments = $project->documents()
            ->active()
            ->where('submission_status', 'draft')
            ->get();

        if ($draftDocuments->isNotEmpty()) {
            $submittedAt = now();
            $documentIds = $draftDocuments->pluck('id');

            $project->documents()
                ->whereIn('id', $documentIds)
                ->update([
                    'submission_status' => 'submitted',
                    'submitted_by' => $request->user()->id,
                    'submitted_at' => $submittedAt,
                    'update_requested_by' => null,
                    'update_requested_at' => null,
                    'update_request_reason' => null,
                ]);

            $project->requirements()
                ->whereIn('document_id', $documentIds)
                ->update([
                    'status' => 'received',
                    'received_by' => $request->user()->id,
                    'received_at' => $submittedAt,
                    'remarks' => 'Submitted with the initial SOI proposal package.',
                    'updated_at' => $submittedAt,
                ]);
        }

        $project = $project->fresh();

        try {
            $reviewers = $this->proposalReviewNotificationRecipients($project);

            app(NotificationService::class)->notifyUsers(
                $reviewers,
                'proposal_submitted',
                "New proposal submitted: {$project->project_code}",
                "{$project->title} was submitted and is ready for SOI review.",
                $project,
                null,
                [
                    'project_title' => $project->title,
                    'submitter_name' => $request->user()->full_name,
                    'stage_name' => $approval->currentStep?->step_name ?? 'SOI review',
                ]
            );
        } catch (\Throwable $notificationException) {
            \Log::warning('New proposal admin notification failed.', [
                'project_id' => $project->id,
                'error' => $notificationException->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Proposal submitted. NDC reviewers have been notified.',
            'approval_started' => true,
            'project' => new ProjectResource($project->load([
                'projectType', 'industry', 'sector', 'currentStage', 'status',
                'projectOfficer', 'workgroupHead', 'creator',
                'members.user', 'members.role', 'requirements',
            ])),
        ]);
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
            $notificationService->notifyProjectStakeholders(
                $project,
                $project->is_archived ? 'project_archived' : 'project_unarchived',
                ($project->is_archived ? 'Project archived: ' : 'Project unarchived: ') . $project->project_code,
                "{$project->title} was " . ($project->is_archived ? 'archived.' : 'restored from archive.'),
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

    public function proponentHistory(Request $request)
    {
        if (!$this->hasGlobalProjectPermission($request->user(), ['projects.view', 'project.view', 'view_project'])) {
            return response()->json(['message' => 'Unauthorized to view proponent project history'], 403);
        }

        $validated = $request->validate([
            'proponent_name' => 'nullable|string|max:255',
            'proponent_email' => 'nullable|email|max:255',
            'exclude_project_id' => 'nullable|integer|exists:projects,id',
        ]);

        $name = trim((string) ($validated['proponent_name'] ?? ''));
        $email = trim((string) ($validated['proponent_email'] ?? ''));

        if ($name === '' && $email === '') {
            return ProjectResource::collection(collect());
        }

        $projects = Project::with([
                'projectType',
                'industry',
                'sector',
                'currentStage',
                'status',
                'creator',
                'proponentUser',
            ])
            ->visibleDraftsTo($request->user())
            ->where('is_deleted', false)
            ->when(!empty($validated['exclude_project_id']), fn ($query) => $query->whereKeyNot($validated['exclude_project_id']))
            ->where(function ($query) use ($name, $email) {
                if ($email !== '') {
                    $query->orWhere('proponent_email', $email);
                }

                if ($name !== '') {
                    $query->orWhere('proponent_name', 'like', "%{$name}%");
                }
            })
            ->latest('updated_at')
            ->limit(8)
            ->get();

        return ProjectResource::collection($projects);
    }

    public function updateRequirement(Request $request, Project $project, ProjectRequirement $requirement)
    {
        if ((int) $requirement->project_id !== (int) $project->id) {
            return response()->json(['message' => 'Requirement does not belong to this project'], 404);
        }

        if (!$this->canManageRequirements($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to update project requirements'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pending,requested,received,deferred,waived,approved,approved_with_conditions,disapproved,for_further_evaluation',
            'remarks' => 'nullable|string',
            'document_id' => 'nullable|exists:documents,id',
            'due_date' => 'nullable|date',
        ]);

        if (
            in_array($validated['status'], ['deferred', 'for_further_evaluation', 'approved_with_conditions', 'disapproved', 'waived'], true)
            && trim((string) ($validated['remarks'] ?? '')) === ''
        ) {
            throw ValidationException::withMessages([
                'remarks' => ['Please add remarks for this requirement decision.'],
            ]);
        }

        if (!empty($validated['document_id'])) {
            $documentBelongsToProject = $project->documents()
                ->whereKey($validated['document_id'])
                ->active()
                ->exists();

            if (!$documentBelongsToProject) {
                return response()->json(['message' => 'Attachment does not belong to this project'], 422);
            }
        }

        $oldStatus = (string) $requirement->status;
        $receivedStatuses = ['received', 'approved', 'approved_with_conditions'];
        $isReceivedStatus = in_array($validated['status'], $receivedStatuses, true);
        $updatePayload = array_merge($validated, [
            'received_at' => $isReceivedStatus ? ($requirement->received_at ?: now()) : null,
            'received_by' => $isReceivedStatus ? ($request->user()?->id ?: $requirement->received_by) : null,
        ]);

        if ($validated['status'] === 'pending') {
            $updatePayload['due_date'] = null;
        }

        $requirement->update($updatePayload);

        if (
            $requirement->document_id
            && in_array($validated['status'], ['deferred', 'for_further_evaluation', 'disapproved'], true)
        ) {
            Document::query()
                ->whereKey($requirement->document_id)
                ->update([
                    'submission_status' => 'update_requested',
                    'update_requested_by' => $request->user()?->id,
                    'update_requested_at' => now(),
                    'update_request_reason' => trim((string) ($validated['remarks'] ?? '')),
                ]);
        }

        $freshRequirement = $requirement->fresh(['document.uploadedBy', 'receivedBy', 'project.creator', 'project.proponentUser']);

        $this->notifyRequirementStatusChanged($freshRequirement, $oldStatus, $request->user());

        return response()->json([
            'message' => 'Requirement updated',
            'requirement' => $freshRequirement,
        ]);
    }

    public function activateMonitoring(Request $request, Project $project)
    {
        if (!$this->canManageRequirements($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to open project monitoring'], 403);
        }

        if ($project->monitoring_status === 'active') {
            return response()->json([
                'message' => 'The monitoring period is already active. Close it before opening a new period.',
            ], 422);
        }

        $validated = $request->validate([
            'due_date' => 'required|date|after_or_equal:today',
            'instructions' => 'required|string|max:5000',
            'proponent_access' => 'nullable|boolean',
        ]);

        if (!$this->isMonitoringEligible($project)) {
            return response()->json([
                'message' => 'Monitoring can only be opened after project approval or when the project has entered implementation.',
            ], 422);
        }

        DB::transaction(function () use ($project, $validated, $request) {
            $project->update([
                'monitoring_status' => 'active',
                'monitoring_submission_status' => 'draft',
                'monitoring_draft_saved_at' => null,
                'monitoring_submitted_at' => null,
                'monitoring_submitted_by' => null,
                'monitoring_reviewed_at' => null,
                'monitoring_reviewed_by' => null,
                'monitoring_review_notes' => null,
                'monitoring_activated_at' => now(),
                'monitoring_activated_by' => $request->user()->id,
                'monitoring_due_date' => $validated['due_date'],
                'monitoring_instructions' => trim($validated['instructions']),
                'monitoring_proponent_access' => $validated['proponent_access'] ?? true,
                'monitoring_closed_at' => null,
            ]);

            $workflow = ApprovalWorkflow::active()
                ->where('name', 'NDC Implementation and Monitoring Workflow')
                ->with('steps')
                ->first();

            if ($workflow && !$project->approvals()->where('workflow_id', $workflow->id)->whereNull('completed_at')->exists()) {
                ProjectApproval::create([
                    'project_id' => $project->id,
                    'workflow_id' => $workflow->id,
                    'current_step_id' => $workflow->steps->first()?->id,
                    'overall_status' => 'milestones_setup',
                    'started_at' => now(),
                ]);
            }

        });

        $project = $project->fresh()->load([
            'projectType', 'industry', 'sector', 'currentStage', 'status',
            'projectOfficer', 'workgroupHead', 'creator', 'proponentUser',
            'monitoringActivatedBy', 'monitoringSubmittedBy', 'monitoringReviewedBy',
            'members.user', 'members.role', 'requirements',
        ]);

        $noticeData = [
            'project_title' => $project->title,
            'project_code' => $project->project_code,
            'due_date' => $project->monitoring_due_date?->format('M d, Y') ?? 'No due date',
            'instructions' => $project->monitoring_instructions,
            'action_url' => $this->projectActionUrl($project, 'monitoring'),
            'action_label' => 'Open Monitoring',
        ];

        $notificationService = app(NotificationService::class);
        $notificationService->notifyProjectProponent(
            $project,
            'monitoring_activated',
            "Monitoring compliance opened: {$project->project_code}",
            "NDC opened monitoring compliance for {$project->title}. Due {$noticeData['due_date']}.",
            'monitoring_request',
            $noticeData
        );

        $internalStakeholders = $notificationService->projectStakeholders($project)
            ->reject(fn (User $user) => $this->isExternalProponent($user))
            ->values();

        $notificationService->notifyUsers(
            $internalStakeholders,
            'monitoring_activated',
            "Monitoring period opened: {$project->project_code}",
            "{$project->title} is now in implementation and monitoring.",
            $project,
            null,
            $noticeData
        );

        return response()->json([
            'message' => 'Monitoring was opened and the proponent was notified.',
            'project' => new ProjectResource($project),
        ]);
    }

    public function implementationReadiness(Request $request, Project $project, ImplementationLifecycleService $service)
    {
        if (! $this->canViewProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to view implementation readiness'], 403);
        }

        return response()->json(['data' => $service->readiness($project)]);
    }

    public function startImplementation(Request $request, Project $project, ImplementationLifecycleService $service)
    {
        if (! $this->canManageRequirements($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to start project implementation'], 403);
        }

        try {
            $project = $service->start($project, $request->user());
        } catch (ImplementationAlreadyStartedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (ImplementationNotReadyException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'blockers' => $exception->blockers,
            ], 422);
        }

        return response()->json([
            'message' => 'Implementation started and the delivery work plan was initialized.',
            'project' => new ProjectResource($project),
        ]);
    }

    public function updateMonitoring(Request $request, Project $project)
    {
        $user = $request->user();
        $isExternalProponent = $this->isExternalProponent($user);
        $isProponentCompliance = $isExternalProponent
            && $project->monitoring_status === 'active'
            && $project->monitoring_proponent_access
            && $this->canViewProject($user, $project);

        if (!$isProponentCompliance && !$this->canManageRequirements($user, $project)) {
            return response()->json(['message' => 'Unauthorized to update monitoring data'], 403);
        }

        if ($project->monitoring_status !== 'active') {
            return response()->json(['message' => 'The monitoring period is not active.'], 422);
        }

        if (
            $isExternalProponent
            && !in_array($project->monitoring_submission_status, ['draft', 'returned'], true)
        ) {
            return response()->json([
                'message' => 'This monitoring report is locked while it is under review or after acceptance.',
            ], 423);
        }

        if ($isExternalProponent && collect([
            'gcg_relevance',
            'gcg_score',
            'reportable_to_gcg',
            'is_reportable',
            'gcg_metrics',
        ])->contains(fn (string $field) => $request->has("financial_metrics.{$field}"))) {
            return response()->json([
                'message' => 'GCG classification and reportability are maintained by NDC reviewers.',
            ], 403);
        }

        $validated = $request->validate([
            'financial_metrics' => 'required|array',
            'financial_metrics.jobs_generated_direct' => 'nullable|integer|min:0',
            'financial_metrics.jobs_generated_indirect' => 'nullable|integer|min:0',
            'financial_metrics.retained_jobs' => 'nullable|integer|min:0',
            'financial_metrics.jobs_direct_male' => 'nullable|integer|min:0',
            'financial_metrics.jobs_direct_female' => 'nullable|integer|min:0',
            'financial_metrics.jobs_indirect_male' => 'nullable|integer|min:0',
            'financial_metrics.jobs_indirect_female' => 'nullable|integer|min:0',
            'financial_metrics.jobs_retained_male' => 'nullable|integer|min:0',
            'financial_metrics.jobs_retained_female' => 'nullable|integer|min:0',
            'financial_metrics.projected_revenue' => 'nullable|numeric|min:0',
            'financial_metrics.actual_revenue' => 'nullable|numeric|min:0',
            'financial_metrics.dividend_remittance' => 'nullable|numeric|min:0',
            'financial_metrics.gcg_relevance' => 'nullable|boolean',
            'financial_metrics.gcg_score' => 'nullable|numeric|min:0',
            'financial_metrics.reportable_to_gcg' => 'nullable|boolean',
            'financial_metrics.is_reportable' => 'nullable|boolean',
            'financial_metrics.monitoring_frequency' => 'nullable|string|max:100',
            'financial_metrics.reporting_period' => 'nullable|string|max:100',
            'financial_metrics.monitoring_indicators' => 'nullable|string|max:10000',
            'financial_metrics.gcg_metrics' => 'nullable|string|max:10000',
            'financial_metrics.social_impact_notes' => 'nullable|string|max:10000',
        ]);

        $projectUpdate = [
            'financial_metrics' => array_merge(
                (array) ($project->financial_metrics ?? []),
                $validated['financial_metrics']
            ),
        ];
        if ($isExternalProponent) {
            $projectUpdate['monitoring_submission_status'] = 'draft';
            $projectUpdate['monitoring_draft_saved_at'] = now();
        }
        $project->update($projectUpdate);

        $freshProject = $project->fresh();
        if (!$isExternalProponent) {
            $notificationService = app(NotificationService::class);
            $recipients = $notificationService->projectStakeholders($freshProject)
                ->reject(fn (User $recipient) => (int) $recipient->id === (int) $user->id)
                ->values();

            $notificationService->notifyUsers(
                $recipients,
            'monitoring_updated',
            "Monitoring report updated: {$project->project_code}",
            "{$user->full_name} updated monitoring indicators for {$project->title}.",
            $freshProject,
            null,
            [
                'project_code' => $freshProject->project_code,
                'project_title' => $freshProject->title,
                'changed_by' => $user->full_name,
                'action_url' => $this->projectActionUrl($freshProject, 'monitoring'),
                'action_label' => 'Open Monitoring',
            ]
        );
        }

        return response()->json([
            'message' => 'Monitoring report saved.',
            'project' => new ProjectResource($freshProject->load([
                'projectType', 'industry', 'sector', 'currentStage', 'status',
                'projectOfficer', 'workgroupHead', 'creator', 'proponentUser',
                'monitoringActivatedBy', 'monitoringSubmittedBy', 'monitoringReviewedBy',
                'members.user', 'members.role', 'requirements',
            ])),
        ]);
    }

    public function submitMonitoring(Request $request, Project $project)
    {
        $user = $request->user();
        if (
            !$this->isExternalProponent($user)
            || !$this->canViewProject($user, $project)
            || !$project->monitoring_proponent_access
        ) {
            return response()->json(['message' => 'Unauthorized to submit this monitoring report'], 403);
        }

        if ($project->monitoring_status !== 'active') {
            return response()->json(['message' => 'The monitoring period is not active.'], 422);
        }

        if (!in_array($project->monitoring_submission_status, ['draft', 'returned'], true)) {
            return response()->json(['message' => 'This monitoring report has already been submitted.'], 409);
        }

        $metrics = collect((array) $project->financial_metrics)
            ->reject(fn ($value) => $value === null || $value === '' || $value === false);
        if ($metrics->isEmpty()) {
            return response()->json([
                'message' => 'Add monitoring results before submitting the report.',
            ], 422);
        }

        $project->update([
            'monitoring_submission_status' => 'submitted',
            'monitoring_submitted_at' => now(),
            'monitoring_submitted_by' => $user->id,
            'monitoring_reviewed_at' => null,
            'monitoring_reviewed_by' => null,
            'monitoring_review_notes' => null,
            'monitoring_proponent_access' => false,
        ]);

        $freshProject = $project->fresh();
        $notificationService = app(NotificationService::class);
        $recipients = $notificationService->projectStakeholders($freshProject)
            ->merge($this->activeAdministratorUsers())
            ->reject(fn (User $recipient) => (int) $recipient->id === (int) $user->id)
            ->unique('id')
            ->values();

        $notificationService->notifyUsers(
            $recipients,
            'monitoring_submitted',
            "Monitoring report submitted: {$project->project_code}",
            "{$user->full_name} submitted the monitoring report for {$project->title}.",
            $freshProject,
            null,
            [
                'project_code' => $freshProject->project_code,
                'project_title' => $freshProject->title,
                'submitted_by' => $user->full_name,
                'submitted_at' => now()->format('M d, Y h:i A'),
                'action_url' => $this->projectActionUrl($freshProject, 'monitoring'),
                'action_label' => 'Review Monitoring',
            ]
        );

        return response()->json([
            'message' => 'Monitoring report submitted to NDC for review.',
            'project' => new ProjectResource($this->loadMonitoringProject($freshProject)),
        ]);
    }

    public function reviewMonitoring(Request $request, Project $project)
    {
        if (!$this->canManageRequirements($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to review monitoring reports'], 403);
        }

        if ($project->monitoring_submission_status !== 'submitted') {
            return response()->json(['message' => 'Only submitted monitoring reports can be reviewed.'], 422);
        }

        $validated = $request->validate([
            'action' => 'required|string|in:accepted,returned',
            'remarks' => 'nullable|string|max:5000|required_if:action,returned',
        ]);

        $accepted = $validated['action'] === 'accepted';
        $project->update([
            'monitoring_submission_status' => $validated['action'],
            'monitoring_reviewed_at' => now(),
            'monitoring_reviewed_by' => $request->user()->id,
            'monitoring_review_notes' => trim((string) ($validated['remarks'] ?? '')),
            'monitoring_proponent_access' => !$accepted,
        ]);

        $freshProject = $project->fresh();
        $event = $accepted ? 'monitoring_accepted' : 'monitoring_returned';
        $message = $accepted
            ? "NDC accepted the monitoring report for {$project->title}."
            : "NDC returned the monitoring report for {$project->title}. Remarks: {$freshProject->monitoring_review_notes}";

        app(NotificationService::class)->notifyProjectProponent(
            $freshProject,
            $event,
            ($accepted ? 'Monitoring report accepted: ' : 'Monitoring report returned: ') . $project->project_code,
            $message,
            null,
            [
                'project_code' => $freshProject->project_code,
                'project_title' => $freshProject->title,
                'reviewed_by' => $request->user()?->full_name ?? 'NDC',
                'remarks' => $freshProject->monitoring_review_notes ?: 'No remarks provided.',
                'action_url' => $this->projectActionUrl($freshProject, 'monitoring'),
                'action_label' => 'Open Monitoring',
            ]
        );

        return response()->json([
            'message' => $accepted
                ? 'Monitoring report accepted.'
                : 'Monitoring report returned to the proponent.',
            'project' => new ProjectResource($this->loadMonitoringProject($freshProject)),
        ]);
    }

    public function monitoringIndex(Request $request)
    {
        if (!$this->isAdministrator($request->user())) {
            return response()->json(['message' => 'Unauthorized to view the monitoring portfolio'], 403);
        }

        $query = Project::query()
            ->with([
                'projectType', 'industry', 'currentStage', 'status', 'creator',
                'proponentUser', 'projectOfficer', 'monitoringSubmittedBy', 'monitoringReviewedBy',
            ])
            ->active()
            ->visibleDraftsTo($request->user())
            ->where('monitoring_status', '!=', 'closed');

        if ($request->filled('submission_status')) {
            $query->where('monitoring_submission_status', $request->get('submission_status'));
        }
        if ($request->boolean('overdue')) {
            $query->whereDate('monitoring_due_date', '<', today())
                ->whereNotIn('monitoring_submission_status', ['accepted']);
        }
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(fn ($searchQuery) => $searchQuery
                ->where('title', 'like', "%{$search}%")
                ->orWhere('project_code', 'like', "%{$search}%")
                ->orWhere('proponent_name', 'like', "%{$search}%"));
        }

        return ProjectResource::collection(
            $query
                ->orderByRaw("CASE monitoring_submission_status
                    WHEN 'submitted' THEN 1
                    WHEN 'returned' THEN 2
                    WHEN 'draft' THEN 3
                    WHEN 'accepted' THEN 4
                    ELSE 5 END")
                ->orderBy('monitoring_due_date')
                ->paginate((int) $request->get('per_page', 20))
        );
    }

    public function closeMonitoring(Request $request, Project $project)
    {
        if (!$this->canManageRequirements($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to close project monitoring'], 403);
        }

        if ($project->monitoring_status !== 'active') {
            return response()->json(['message' => 'The monitoring period is not active.'], 422);
        }

        if ($project->monitoring_submission_status !== 'accepted') {
            return response()->json([
                'message' => 'Accept the submitted monitoring report before closing the period.',
            ], 422);
        }

        $project->update([
            'monitoring_status' => 'completed',
            'monitoring_proponent_access' => false,
            'monitoring_closed_at' => now(),
        ]);

        $project->approvals()
            ->whereHas('workflow', fn ($query) => $query->where('name', 'NDC Implementation and Monitoring Workflow'))
            ->whereNull('completed_at')
            ->update([
                'overall_status' => 'completed',
                'completed_at' => now(),
                'current_step_id' => null,
            ]);

        app(NotificationService::class)->notifyProjectStakeholders(
            $project->fresh(),
            'monitoring_closed',
            "Monitoring period closed: {$project->project_code}",
            "NDC closed the monitoring period for {$project->title}.",
            null,
            [
                'project_code' => $project->project_code,
                'project_title' => $project->title,
                'action_url' => $this->projectActionUrl($project, 'monitoring'),
                'action_label' => 'Open Monitoring',
            ]
        );

        return response()->json([
            'message' => 'Monitoring period closed.',
            'project' => new ProjectResource($project->fresh()->load([
                'projectType', 'industry', 'sector', 'currentStage', 'status',
                'projectOfficer', 'workgroupHead', 'creator', 'proponentUser',
                'monitoringActivatedBy', 'monitoringSubmittedBy', 'monitoringReviewedBy',
                'members.user', 'members.role', 'requirements',
            ])),
        ]);
    }

    public function uploadImages(Request $request, Project $project)
    {
        if (!$this->canEditProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to upload project images'], 403);
        }

        $validated = $request->validate([
            'images' => 'required|array|min:1|max:12',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192',
            'title' => 'nullable|string|max:255',
            'set_thumbnail' => 'nullable|boolean',
        ]);

        $uploaded = [];
        DB::beginTransaction();

        try {
            $maxSort = (int) $project->images()->max('sort_order');
            $hasThumbnail = $project->images()->where('is_thumbnail', true)->exists();
            $makeFirstThumbnail = $request->boolean('set_thumbnail') || !$hasThumbnail;

            foreach ($validated['images'] as $index => $file) {
                $path = $file->store("project-images/{$project->id}", 'public');
                $isThumbnail = $makeFirstThumbnail && $index === 0;

                if ($isThumbnail) {
                    $project->images()->update(['is_thumbnail' => false]);
                }

                $image = ProjectImage::create([
                    'project_id' => $project->id,
                    'title' => $validated['title'] ?? null,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getMimeType(),
                    'is_thumbnail' => $isThumbnail,
                    'sort_order' => $maxSort + $index + 1,
                    'uploaded_by' => $request->user()?->id,
                    'uploaded_at' => now(),
                ]);

                if ($isThumbnail) {
                    $project->update(['thumbnail_url' => $path]);
                }

                $uploaded[] = $image;
            }

            DB::commit();

            return response()->json([
                'message' => count($uploaded) === 1 ? 'Project image uploaded' : 'Project images uploaded',
                'images' => ProjectImageResource::collection(
                    ProjectImage::with('uploadedBy')->whereIn('id', collect($uploaded)->pluck('id'))->get()
                ),
                'project' => new ProjectResource($project->fresh()->load([
                    'projectType', 'industry', 'sector', 'investmentType', 'fundingSource',
                    'currentStage', 'status', 'projectOfficer', 'workgroupHead', 'creator',
                    'members.user', 'members.role', 'images.uploadedBy',
                ])),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            foreach ($uploaded as $image) {
                if ($image instanceof ProjectImage && $image->file_path) {
                    Storage::disk('public')->delete($image->file_path);
                }
            }

            return response()->json([
                'message' => 'Failed to upload project images',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function setThumbnailImage(Request $request, Project $project, ProjectImage $image)
    {
        if ((int) $image->project_id !== (int) $project->id || $image->is_deleted) {
            return response()->json(['message' => 'Image does not belong to this project'], 404);
        }

        if (!$this->canEditProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to update project thumbnail'], 403);
        }

        DB::transaction(function () use ($project, $image) {
            $project->images()->update(['is_thumbnail' => false]);
            $image->update(['is_thumbnail' => true]);
            $project->update(['thumbnail_url' => $image->file_path]);
        });

        return response()->json([
            'message' => 'Map thumbnail updated',
            'image' => new ProjectImageResource($image->fresh('uploadedBy')),
            'project' => new ProjectResource($project->fresh()->load(['images.uploadedBy'])),
        ]);
    }

    public function deleteImage(Request $request, Project $project, ProjectImage $image)
    {
        if ((int) $image->project_id !== (int) $project->id || $image->is_deleted) {
            return response()->json(['message' => 'Image does not belong to this project'], 404);
        }

        if (!$this->canEditProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to delete project images'], 403);
        }

        DB::transaction(function () use ($project, $image) {
            $wasThumbnail = (bool) $image->is_thumbnail;
            $image->update([
                'is_deleted' => true,
                'deleted_at' => now(),
                'is_thumbnail' => false,
            ]);

            if ($wasThumbnail) {
                $next = $project->images()->first();
                if ($next) {
                    $next->update(['is_thumbnail' => true]);
                    $project->update(['thumbnail_url' => $next->file_path]);
                } else {
                    $project->update(['thumbnail_url' => null]);
                }
            }
        });

        return response()->json([
            'message' => 'Project image deleted',
            'project' => new ProjectResource($project->fresh()->load(['images.uploadedBy'])),
        ]);
    }

    /**
     * Generate unique project code.
     */
    private function generateProjectCode(array $projectPayload = [])
    {
        $prefix = $this->projectCodePrefix($projectPayload);
        $year = date('Y');

        // Include soft-deleted projects to avoid reusing codes from archived/deleted records.
        $maxNumber = Project::withTrashed()
            ->where('project_code', 'like', "{$prefix}-{$year}-%")
            ->pluck('project_code')
            ->map(function ($code) use ($prefix, $year) {
                $pattern = '/^' . preg_quote($prefix, '/') . '-' . preg_quote((string) $year, '/') . '-(\d+)$/';
                return preg_match($pattern, (string) $code, $matches) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        $nextNumber = $maxNumber + 1;
        $newNumber = str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$newNumber}";
    }

    private function projectCodePrefix(array $projectPayload): string
    {
        if ((bool) ($projectPayload['is_svf'] ?? false)) {
            return 'SVF';
        }

        return match ((string) ($projectPayload['process_track'] ?? 'bdg_investment')) {
            'spg_traditional', 'spg_ndc_own', 'spg_jv' => 'SPG',
            'implementation_monitoring' => 'MON',
            'divestment' => 'DIV',
            default => 'BDG',
        };
    }

    private function isDuplicateProjectCodeError(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            && str_contains($e->getMessage(), 'projects_project_code_unique');
    }

    private function hasAnyPermission(?User $user, array $permissionNames): bool
    {
        if (!$user) return false;

        if ($this->isSuperAdmin($user)) {
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

    private function canCreateProject(?User $user): bool
    {
        return $this->hasAnyPermission($user, ['projects.create', 'project.create', 'create_project']);
    }

    private function canViewProject(?User $user, Project $project): bool
    {
        if (!$user) return false;

        if ($this->isDraftOwnedByAnotherUser($user, $project)) {
            return false;
        }

        if ($this->isSuperAdmin($user) || $this->hasGlobalProjectPermission($user, ['projects.view', 'project.view', 'view_project'])) {
            return true;
        }

        if ((int) $project->created_by === (int) $user->id) {
            return true;
        }

        $member = $this->getActiveMember($project, $user->id);
        return (bool)($member && $member->can_view);
    }

    private function canEditProject(?User $user, Project $project): bool
    {
        if (!$user) return false;

        if ($this->isDraftOwnedByAnotherUser($user, $project)) {
            return false;
        }

        if ($this->isSuperAdmin($user) || $this->hasGlobalProjectPermission($user, ['projects.update', 'projects.edit', 'project.update', 'project.edit', 'edit_project'])) {
            return true;
        }

        $member = $this->getActiveMember($project, $user->id);
        return (bool)($member && $member->can_edit);
    }

    private function isDraftOwnedByAnotherUser(User $user, Project $project): bool
    {
        $project->loadMissing('status');

        return strcasecmp((string) $project->status?->name, 'Draft') === 0
            && (int) $project->created_by !== (int) $user->id;
    }

    private function isSuperAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $roleName = strtolower((string) ($user->defaultRole?->name ?? ''));

        return (int)$user->default_role_id === 1 || $roleName === 'superadmin';
    }

    private function isExternalProponent(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $roleName = strtolower((string) ($user->defaultRole?->name ?? ''));

        return (int) $user->default_role_id === 7 || $roleName === 'proponent';
    }

    private function resolveInternalProjectUserId(?User $creator, array $preferredRoleIds, int $fallbackId): int
    {
        if ($creator && !$this->isExternalProponent($creator)) {
            return (int) $creator->id;
        }

        foreach ($preferredRoleIds as $roleId) {
            $userId = User::query()
                ->active()
                ->where('default_role_id', $roleId)
                ->value('id');

            if ($userId) {
                return (int) $userId;
            }
        }

        return $fallbackId;
    }

    private function applyReportPreset($query, string $preset): void
    {
        match ($preset) {
            'approved' => $query->where(function ($presetQuery) {
                $presetQuery
                    ->whereHas('status', fn ($statusQuery) => $statusQuery->whereIn('name', [
                        'Approved',
                        'Approved with Conditions',
                        'For Agreement Signing',
                        'For Fund Release',
                    ]))
                    ->orWhereHas('approvals', fn ($approvalQuery) => $approvalQuery->whereIn('overall_status', [
                        'approved',
                        'approved_with_conditions',
                    ]));
            }),
            'ongoing' => $query->where(function ($presetQuery) {
                $presetQuery
                    ->whereHas('status', fn ($statusQuery) => $statusQuery
                        ->where('name', 'like', '%Ongoing%')
                        ->orWhereIn('name', [
                            'Requirements Requested',
                            'Requirements Received',
                            'Due Diligence Ongoing',
                            'For IC Evaluation',
                            'For Workgroup Review',
                            'For ManCom Review',
                            'For Board Approval',
                            'For Agreement Signing',
                            'For Fund Release',
                        ]))
                    ->orWhereHas('currentStage', fn ($stageQuery) => $stageQuery->whereIn('name', [
                        'Requirements',
                        'Due Diligence',
                        'Management Review',
                        'Board Approval',
                        'Agreement & Fund Release',
                        'Implementation & Monitoring',
                        'Post-Investment Strategy',
                        'Divestment',
                    ]));
            }),
            'completed' => $query->where(function ($presetQuery) {
                $presetQuery
                    ->whereNotNull('actual_completion_date')
                    ->orWhereHas('status', fn ($statusQuery) => $statusQuery->whereIn('name', [
                        'Completed',
                        'Archived',
                        'Divested',
                    ]))
                    ->orWhereHas('currentStage', fn ($stageQuery) => $stageQuery->whereIn('name', [
                        'Completion',
                        'Divestment',
                    ]))
                    ->orWhereHas('approvals', fn ($approvalQuery) => $approvalQuery->where('overall_status', 'completed'));
            }),
            'categorized' => $query->whereNotNull('project_type_id')
                ->whereNotNull('industry_id')
                ->whereNotNull('sector_id'),
            'reportable' => $query->where(function ($presetQuery) {
                $presetQuery
                    ->where('financial_metrics->reportable_to_gcg', true)
                    ->orWhere('financial_metrics->is_reportable', true)
                    ->orWhereHas('currentStage', fn ($stageQuery) => $stageQuery->whereIn('name', [
                        'Implementation & Monitoring',
                        'Post-Investment Strategy',
                        'Divestment',
                        'Completion',
                    ]))
                    ->orWhereHas('status', fn ($statusQuery) => $statusQuery->whereIn('name', [
                        'Approved',
                        'Approved with Conditions',
                        'Implementation Ongoing',
                        'Monitoring Ongoing',
                        'Completed',
                    ]));
            }),
            default => null,
        };
    }

    private function hasGlobalProjectPermission(?User $user, array $permissionNames): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($this->isExternalProponent($user)) {
            return false;
        }

        return $this->hasAnyPermission($user, $permissionNames);
    }

    private function activeAdministratorUsers()
    {
        return User::active()
            ->where(function ($query) {
                $query
                    ->where('default_role_id', 1)
                    ->orWhereHas('defaultRole', fn ($roleQuery) => $roleQuery
                        ->whereRaw('LOWER(name) in (?, ?)', ['superadmin', 'admin']));
            })
            ->get();
    }

    private function proposalReviewNotificationRecipients(Project $project)
    {
        $project->loadMissing(['projectOfficer', 'workgroupHead', 'members.user']);

        $ndcReviewRoleIds = [1, 2, 3, 4, 5, 6, 8, 9, 10];
        $roleUsers = User::active()
            ->whereIn('default_role_id', $ndcReviewRoleIds)
            ->get();

        $projectReviewUsers = collect([
            $project->projectOfficer,
            $project->workgroupHead,
        ])->merge(
            $project->members
                ->whereNull('removed_at')
                ->filter(fn ($member) => $member->can_approve || $member->can_edit)
                ->pluck('user')
        );

        return $projectReviewUsers
            ->merge($roleUsers)
            ->filter()
            ->unique('id')
            ->values();
    }

    private function isAdministrator(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $roleName = strtolower((string) ($user->defaultRole?->name ?? ''));

        return (int) $user->default_role_id === 1
            || in_array($roleName, ['superadmin', 'admin'], true);
    }

    private function loadMonitoringProject(Project $project): Project
    {
        return $project->load([
            'projectType', 'industry', 'sector', 'currentStage', 'status',
            'projectOfficer', 'workgroupHead', 'creator', 'proponentUser',
            'monitoringActivatedBy', 'monitoringSubmittedBy', 'monitoringReviewedBy',
            'members.user', 'members.role', 'requirements',
        ]);
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

        return $approval->overall_status !== 'returned';
    }

    private function canDeleteProject(?User $user, Project $project): bool
    {
        if (!$user) return false;

        if ($this->isDraftOwnedByAnotherUser($user, $project)) {
            return false;
        }

        if ($this->hasAnyPermission($user, ['projects.delete', 'project.delete', 'delete_project'])) {
            return true;
        }

        $member = $this->getActiveMember($project, $user->id);
        return (bool)($member && $member->can_delete);
    }

    private function canManageMembers(?User $user, Project $project): bool
    {
        if (!$user) return false;

        if ($this->isDraftOwnedByAnotherUser($user, $project)) {
            return false;
        }

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

    private function canManageRequirements(?User $user, Project $project): bool
    {
        if (!$user) return false;

        if ($this->isDraftOwnedByAnotherUser($user, $project)) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($this->isExternalProponent($user)) {
            return false;
        }

        if ($this->hasAnyPermission($user, [
            'documents.review',
            'documents.update',
            'projects.update',
            'project.update',
            'project.edit',
            'edit_project',
        ])) {
            return true;
        }

        if ((int) $project->project_officer_id === (int) $user->id || (int) $project->workgroup_head_id === (int) $user->id) {
            return true;
        }

        $member = $this->getActiveMember($project, $user->id);
        return (bool) ($member && $member->can_approve);
    }

    private function isMonitoringEligible(Project $project): bool
    {
        return in_array($project->lifecycle_phase, [
            'implementation_monitoring',
            'post_investment',
            'divestment',
            'completed',
        ], true);
    }

    private function moveProjectToMonitoringStage(Project $project, int $actorId): void
    {
        $stageId = ProjectStage::query()->where('name', 'Implementation & Monitoring')->value('id');
        $statusId = ProjectStatus::query()->where('name', 'Monitoring Ongoing')->value('id')
            ?: ProjectStatus::query()->where('name', 'Implementation Ongoing')->value('id');

        $oldStageId = $project->current_stage_id;
        $oldStatusId = $project->status_id;

        $project->update(array_filter([
            'current_stage_id' => $stageId,
            'status_id' => $statusId,
        ]));

        if ($stageId && (int) $oldStageId !== (int) $stageId) {
            ProjectStageHistory::create([
                'project_id' => $project->id,
                'from_stage_id' => $oldStageId,
                'to_stage_id' => $stageId,
                'changed_by' => $actorId,
                'change_reason' => 'Implementation and monitoring period activated',
            ]);
        }

        if ($statusId && (int) $oldStatusId !== (int) $statusId) {
            ProjectStatusHistory::create([
                'project_id' => $project->id,
                'from_status_id' => $oldStatusId,
                'to_status_id' => $statusId,
                'changed_by' => $actorId,
                'change_reason' => 'Implementation and monitoring period activated',
            ]);
        }
    }

    private function notifyRequirementStatusChanged(ProjectRequirement $requirement, string $oldStatus, ?User $actor): void
    {
        if ($oldStatus === (string) $requirement->status) {
            return;
        }

        if (($requirement->visibility ?: 'proponent_visible') === 'internal_only') {
            return;
        }

        $notifiableStatuses = [
            'requested',
            'deferred',
            'waived',
            'approved',
            'approved_with_conditions',
            'for_further_evaluation',
            'disapproved',
        ];

        if (!in_array((string) $requirement->status, $notifiableStatuses, true)) {
            return;
        }

        $project = $requirement->project;
        if (!$project) {
            return;
        }

        $statusLabel = $this->formatRequirementStatusForNotice((string) $requirement->status);
        $titleMap = [
            'requested' => "Requirement requested: {$project->project_code}",
            'deferred' => "Requirement returned: {$project->project_code}",
            'waived' => "Requirement waived: {$project->project_code}",
            'approved' => "Requirement accepted: {$project->project_code}",
            'approved_with_conditions' => "Requirement condition noted: {$project->project_code}",
            'for_further_evaluation' => "Requirement needs follow-up: {$project->project_code}",
            'disapproved' => "Requirement disapproved: {$project->project_code}",
        ];

        $dueDate = $requirement->due_date ? $requirement->due_date->format('M d, Y') : 'No due date set';
        $remarks = trim((string) $requirement->remarks);
        $message = "{$requirement->item_name} is now {$statusLabel} for {$project->title}.";
        $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://127.0.0.1:3000'), '/');
        $actionUrl = "{$frontendUrl}/projects?project_id={$project->id}&tab=requirements&requirement_id={$requirement->id}";
        $requestAction = match ((string) $requirement->status) {
            'requested' => 'requested',
            'deferred' => 'returned for update',
            'for_further_evaluation' => 'requested follow-up on',
            'approved' => 'accepted',
            'approved_with_conditions' => 'accepted with conditions for',
            'waived' => 'waived',
            'disapproved' => 'disapproved',
            default => 'updated',
        };

        if ($requirement->status === 'requested') {
            $message = "NDC requested {$requirement->item_name} for {$project->title}. Due date: {$dueDate}.";
        }

        if ($remarks !== '') {
            $message .= " Remarks: {$remarks}";
        }

        try {
            app(NotificationService::class)->notifyProjectProponent(
                $project,
                'requirement_status_change',
                $titleMap[$requirement->status] ?? "Requirement updated: {$project->project_code}",
                $message,
                'requirement_status_change',
                [
                    'project_title' => $project->title,
                    'project_code' => $project->project_code,
                    'requirement_name' => $requirement->item_name,
                    'requirement_group' => $requirement->group_name,
                    'old_status' => $this->formatRequirementStatusForNotice($oldStatus),
                    'new_status' => $statusLabel,
                    'changed_by' => $actor?->full_name ?? 'System',
                    'due_date' => $dueDate,
                    'remarks' => $remarks !== '' ? $remarks : "Please upload {$requirement->item_name} for NDC review.",
                    'request_action' => $requestAction,
                    'action_url' => $actionUrl,
                    'action_label' => $requirement->status === 'requested' ? 'Upload Requested File' : 'Open Requirement',
                ]
            );
        } catch (\Throwable $notificationException) {
            \Log::warning('Requirement status notification failed.', [
                'project_id' => $project->id,
                'requirement_id' => $requirement->id,
                'old_status' => $oldStatus,
                'new_status' => $requirement->status,
                'error' => $notificationException->getMessage(),
            ]);
        }
    }

    private function formatRequirementStatusForNotice(string $status): string
    {
        return collect(explode('_', $status))
            ->filter()
            ->map(fn ($word) => ucfirst($word))
            ->join(' ');
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

        if ($this->requiresPackageSubmissionBeforeApproval($payload['process_track'] ?? 'bdg_investment', (bool) ($payload['is_svf'] ?? false))) {
            $payload['status_id'] = ProjectStatus::where('name', 'Draft')->value('id') ?? $payload['status_id'];
        }

        return $payload;
    }

    private function shouldStartApprovalOnCreate(Project $project): bool
    {
        return !$this->requiresPackageSubmissionBeforeApproval(
            (string) ($project->process_track ?: 'bdg_investment'),
            (bool) $project->is_svf
        );
    }

    private function requiresPackageSubmissionBeforeApproval(string $track, bool $isSvf = false): bool
    {
        return $isSvf || in_array($track, ['bdg_investment', 'spg_traditional', 'spg_jv'], true);
    }

    private function seedDefaultRequirements(Project $project): void
    {
        $track = (string) ($project->process_track ?: 'bdg_investment');
        $isSvf = (bool) $project->is_svf;
        
        $defaults = DefaultRequirement::where('track', $track)
            ->where(function ($query) use ($isSvf) {
                if (!$isSvf) {
                    $query->where('svf_only', false);
                }
            })
            ->orderBy('sort_order')
            ->get();
            
        foreach ($defaults as $item) {
            $isInitialSubmissionItem = $item->owner_type === 'proponent'
                && in_array($item->soi_section, ['intake', 'requirements'], true);
            $status = $isInitialSubmissionItem ? 'requested' : 'pending';
            
            $project->requirements()->updateOrCreate(
                [
                    'group_name' => $item->group_name,
                    'item_name' => $item->item_name,
                ],
                [
                    'source_document' => $item->source_document,
                    'track' => $item->track,
                    'owner_type' => $item->owner_type,
                    'visibility' => $item->visibility,
                    'soi_section' => $item->soi_section,
                    'gate_step' => $item->gate_step,
                    'is_required' => $item->is_required,
                    'svf_only' => $item->svf_only,
                    'sort_order' => $item->sort_order,
                    'template_file_path' => $item->template_file_path,
                    'status' => $status,
                ]
            );
        }
    }

    private function defaultRequirementItems(string $track, bool $isSvf): array
    {
        $trackSpecificItems = match ($track) {
            'spg_jv' => [
                ['1. JV Concept Package', 'Formal project concept or JV proposal submitted for initial SPG review', 'SPG Proposal Requirements', false, [
                    'owner_type' => 'proponent',
                    'visibility' => 'proponent_visible',
                    'soi_section' => 'intake',
                    'gate_step' => null,
                    'is_required' => true,
                    'status' => 'requested',
                ]],
                ['1. JV Concept Package', 'Company profile, capability statement, or pitch deck', 'SPG Proposal Requirements', false, [
                    'owner_type' => 'proponent',
                    'visibility' => 'proponent_visible',
                    'soi_section' => 'intake',
                    'gate_step' => null,
                    'is_required' => true,
                    'status' => 'requested',
                ]],
                ['1. JV Concept Package', 'Authority to submit or authorized representative certification', 'SPG Proposal Requirements', false, [
                    'owner_type' => 'proponent',
                    'visibility' => 'proponent_visible',
                    'soi_section' => 'intake',
                    'gate_step' => null,
                    'is_required' => true,
                    'status' => 'requested',
                ]],
                ['2. ManCom Approval to Proceed', 'JV project concept and initial evaluation note', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'intake',
                    'gate_step' => null,
                    'is_required' => true,
                    'status' => 'pending',
                ]],
                ['2. ManCom Approval to Proceed', 'ManCom approval to proceed with study and budget allocation', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'management_review',
                    'gate_step' => null,
                    'is_required' => true,
                    'status' => 'pending',
                ]],
                ['3. Consultancy Procurement and Study', 'Budget estimates, TOR, Materials Requisition, and bidding documents', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'due_diligence',
                    'gate_step' => null,
                    'is_required' => true,
                    'status' => 'pending',
                ]],
                ['3. Consultancy Procurement and Study', 'Consultancy agreement, Notice to Proceed, and study report', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'due_diligence',
                    'gate_step' => 'spg_jv_mancom_project_decision',
                    'is_required' => true,
                    'status' => 'pending',
                ]],
                ['4. ManCom JV Project Decision', 'Study evaluation, recommendation, and ManCom presentation material', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'management_review',
                    'gate_step' => 'spg_jv_mancom_project_decision',
                    'is_required' => true,
                    'status' => 'pending',
                ]],
                ['4. ManCom JV Project Decision', 'ManCom decision and endorsement to the Board', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_board_project_approval',
                    'is_required' => true,
                    'status' => 'pending',
                ]],
                ['5. Board Approval of JV Project', 'Board paper and approval package for the JV project', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_board_project_approval',
                    'is_required' => true,
                    'status' => 'pending',
                ]],
                ['5. Board Approval of JV Project', 'Board approval record or Secretary Certificate for the JV project', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_neda_icc',
                    'is_required' => true,
                    'status' => 'pending',
                ]],
                ['6. NEDA-ICC and JV-SC', 'NEDA-ICC requirements and endorsement package if required', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_neda_icc',
                    'is_required' => true,
                    'status' => 'pending',
                ]],
                ['6. NEDA-ICC and JV-SC', 'NEDA-ICC approval record or applicability waiver', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_jva_terms_jvsc',
                    'is_required' => true,
                    'status' => 'pending',
                ]],
                ['6. NEDA-ICC and JV-SC', 'Board approval of NEDA-approved JVA terms and JV-SC composition', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_selection_award',
                    'is_required' => true,
                    'status' => 'pending',
                ]],
                ['7. JV Partner Selection and Award', 'JV selection documents, IAESP, publication, and eligibility records', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_selection_award',
                    'is_required' => true,
                    'status' => 'pending',
                ]],
                ['7. JV Partner Selection and Award', 'JV-SC selection proceedings and recommendation to award', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_final_award',
                    'is_required' => true,
                    'status' => 'pending',
                ]],
                ['7. JV Partner Selection and Award', 'Board approval of award and Notice of Award', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_jva_signing',
                    'is_required' => true,
                    'status' => 'pending',
                ]],
                ['8. NOA and JVA Signing', 'Signed Joint Venture Agreement', 'SPG JV Tracking Sheet', false, [
                    'owner_type' => 'internal',
                    'visibility' => 'internal_only',
                    'soi_section' => 'agreement_fund_release',
                    'gate_step' => null,
                    'is_required' => true,
                    'status' => 'pending',
                ]],
            ],
            'spg_ndc_own' => [
                ['1. Concept and ManCom Instruction', 'Project concept and initial evaluation report', 'SPG SOI-01 Section 8', false, ['soi_section' => 'intake', 'gate_step' => null, 'status' => 'pending']],
                ['1. Concept and ManCom Instruction', 'ManCom approval to proceed with study or consultancy', 'SPG SOI-01 Section 8', false, ['soi_section' => 'management_review', 'gate_step' => null, 'status' => 'pending']],
                ['2. Consultancy Study Procurement', 'Budget estimates, TOR, Materials Requisition, and bidding documents', 'SPG tracking sheet - NDC on its own', false, ['soi_section' => 'due_diligence', 'gate_step' => null, 'status' => 'pending']],
                ['2. Consultancy Study Procurement', 'Consultancy agreement, NTP, and study report', 'SPG SOI-01 Section 8', false, ['soi_section' => 'due_diligence', 'gate_step' => 'spg_ndc_own_mancom_project_decision', 'status' => 'pending']],
                ['3. Management and Board Decision', 'Study evaluation, recommendation, and ManCom presentation material', 'SPG tracking sheet - NDC on its own', false, ['soi_section' => 'management_review', 'gate_step' => 'spg_ndc_own_mancom_project_decision', 'status' => 'pending']],
                ['3. Management and Board Decision', 'ManCom decision, directives, and endorsement to the Board', 'SPG tracking sheet - NDC on its own', false, ['soi_section' => 'board_approval', 'gate_step' => 'spg_ndc_own_board_approval', 'status' => 'pending']],
                ['3. Management and Board Decision', 'Board paper, Board Resolution, or Secretary Certificate', 'SPG SOI-01 Section 8', false, ['soi_section' => 'board_approval', 'gate_step' => 'spg_ndc_own_ded_construction', 'status' => 'pending']],
                ['4. DED and Construction', 'DED TOR/MR, bidding documents, design plans, and specifications', 'SPG tracking sheet - NDC on its own', false, ['soi_section' => 'agreement_fund_release', 'gate_step' => 'spg_ndc_own_ded_construction', 'status' => 'pending']],
                ['4. DED and Construction', 'Construction contract, supervision agreement, completion acceptance, and turn-over record', 'SPG SOI-01 Section 8', false, ['soi_section' => 'implementation_monitoring', 'gate_step' => null, 'status' => 'pending']],
            ],
            'implementation_monitoring' => [
                ['1. Summary Folder', 'Signed agreements, Board approval, release-of-funds records, and project brief', 'BDG/SPG implementation SOI', false],
                ['1. Summary Folder', 'Schedule of drawdowns, covenants, dividends/coupons, amortization, or repayment terms', 'BDG/SPG implementation SOI', false],
                ['2. Monitoring Evidence', 'Correspondence, statements of account, financial statements, Board papers, and risk/compliance registers', 'BDG/SPG implementation SOI', false],
                ['2. Monitoring Evidence', 'Milestone, issues, next steps, quarterly management/COA update, and post-investment notes', 'BDG/SPG implementation SOI', false],
                ['3. Adjustment / Modification Approval', 'ManCom or Board endorsement records for restructuring, equity changes, or divestment adjustments', 'BDG/SPG implementation SOI', false],
            ],
            'divestment' => [
                ['1. Divestment Start', 'ManCom-approved divestment recommendation, external offer, or authority to proceed', 'SPG SOI-03 Section 6.1', false],
                ['2. Due Diligence', 'Legal due diligence report or legal memo', 'SPG SOI-03 Section 6.2', false],
                ['2. Due Diligence', 'Financial due diligence, asset appraisal, pricing basis, and updated financial statements', 'SPG SOI-03 Section 6.2', false],
                ['3. Approvals', 'ManCom paper and approval of proposed transfer terms', 'SPG SOI-03 Section 6.3', false],
                ['3. Approvals', 'Board paper, Board approval, or Secretary Certificate', 'SPG SOI-03 Section 6.4', false],
                ['4. Transfer and Collection', 'Transfer documents, payment evidence, receipts, and closing records', 'SPG SOI-03 Section 6.5', false],
            ],
            default => null,
        };

        if ($trackSpecificItems !== null) {
            return collect($trackSpecificItems)
                ->values()
                ->map(function ($item, $index) use ($track) {
                    $metadata = array_merge(
                        $this->requirementSoiMetadata($item[0], $item[1], true),
                        $item[4] ?? []
                    );

                    return array_merge([
                        'group_name' => $item[0],
                        'item_name' => $item[1],
                        'source_document' => $item[2],
                        'track' => $track,
                        'is_applicable' => true,
                        'svf_only' => (bool) $item[3],
                        'status' => 'requested',
                        'sort_order' => ($index + 1) * 10,
                    ], $metadata);
                })
                ->all();
        }

        $items = [
            ['1. Intake Pack', 'Brochure, pitch deck, or company profile', 'BDG/SPG SOI', false],
            ['1. Intake Pack', 'Website or product/company page', 'BDG eligibility checklist', false],
            ['1. Intake Pack', 'Letter of Intent or project concept', 'Proposal requirements', false],
            ['1. Intake Pack', 'Non-Disclosure Agreement and Data Privacy Consent', 'NDC templates', false],
            ['1. Intake Pack', 'Secretary Certificate or authority to submit', 'Checklist of requirements', false],

            ['2. NDC Screening / Response', 'Response letter and documentary checklist issued to proponent', 'BDG checklist / SPG official checklist', false],
            ['2. Proposal Summary', 'Project description, technology, location, and market reason', '1st level proposal format', false],
            ['2. Proposal Summary', 'Target beneficiaries, social/economic benefits, and jobs generated', '1st level proposal format', false],
            ['2. Proposal Summary', 'Estimated project cost, projected revenue, NDC participation, and schedule', '1st level proposal format', false],
            ['2. Proposal Summary', 'Proponent background, shareholders, affiliates, and track record', '1st level proposal format', false],

            ['3. Company / Legal / Financial Documents', 'SEC or DTI registration, Articles, and By-Laws', 'Official checklist', false],
            ['3. Company / Legal / Financial Documents', 'Audited financial statements for the last three years, BIR, and tax clearance', 'Official checklist', false],
            ['3. Company / Legal / Financial Documents', 'Proof of site ownership, authority, or project location control', 'BDG SOI', false],

            ['4. Evaluation Documents', 'Feasibility Study, Pre-FS, or Business Plan', '2nd level proposal format', false],
            ['4. Evaluation Documents', 'Financial model, profitability analysis, and use of proceeds', '2nd level proposal format', false],
            ['4. Evaluation Documents', 'Risk register, ESG/GAD write-up if applicable, and mitigation plan', 'Proposal requirements', false],
            ['4. Evaluation Documents', 'Due diligence or credit/background investigation report', 'BDG SOI', false],
            ['4. Evaluation Documents', 'Investment criteria assessment with at least three qualifying criteria', 'Official checklist', false],

            ['5. Internal Evaluation / Endorsement', 'Investment Committee evaluation material', 'BDG SOI - SVF only', true],
            ['5. Internal Evaluation / Endorsement', 'ManCom decision paper, recommendation, or presentation material', 'BDG/SPG SOI', false],
            ['5. Internal Evaluation / Endorsement', 'ManCom decision and endorsement to the Board', 'SPG official checklist / tracking sheet', false],
            ['6. Board Evaluation', 'Board paper and approval package', 'BDG/SPG SOI', false],
            ['6. Board Evaluation', 'Board Resolution or Secretary Certificate', 'BDG checklist', false],
            ['7. Agreement and Fund Release', 'Investment Agreement, contract, JVA, or signed transaction document', 'Official checklist', false],
            ['7. Agreement and Fund Release', 'Receipt issued by investee company or fund release evidence', 'BDG checklist', false],

            ['8. Monitoring', 'Project Summary Sheet with milestones, covenants, risks, issues, and next steps', 'Implementation SOI', false],
            ['8. Monitoring', 'Jobs generated, financial updates, and quarterly reporting evidence', 'NDC templates / COA tracking sheet', false],
        ];

        if ($track === 'spg_jv') {
            $items[] = ['JV Requirements', 'NEDA endorsement / ICC requirements as applicable', 'SPG JV SOI', false];
            $items[] = ['JV Requirements', 'NEDA-ICC approval record and Board approval of JVA terms / JV-SC composition', 'SPG JV tracking sheet', false];
            $items[] = ['JV Requirements', 'JV Selection Committee documents, Notice of Award, and signed JVA', 'SPG JV SOI', false];
        }

        if ($track === 'spg_ndc_own') {
            $items[] = ['NDC-Owned Project', 'ManCom approval to proceed with study or consultancy', 'NDC-on-Own SOI', false];
            $items[] = ['NDC-Owned Project', 'Procurement, bidding, DED, construction, and turn-over documents', 'NDC-on-Own SOI', false];
        }

        if ($track === 'divestment') {
            $items[] = ['Divestment', 'Legal due diligence report / legal memo', 'Divestment SOI', false];
            $items[] = ['Divestment', 'Financial due diligence and updated financial statements', 'Divestment SOI', false];
            $items[] = ['Divestment', 'Transfer documents, collection evidence, and receipts', 'Divestment SOI', false];
        }

        return collect($items)
            ->filter(fn ($item) => !$item[3] || $isSvf)
            ->values()
            ->map(function ($item, $index) use ($track) {
                $isRequiredForInitialSubmission = $this->isInitialSubmissionRequirement($item[0], $item[1]);
                $metadata = $this->requirementSoiMetadata($item[0], $item[1], $isRequiredForInitialSubmission);

                return [
                    'group_name' => $item[0],
                    'item_name' => $item[1],
                    'source_document' => $item[2],
                    'track' => $track,
                    'is_applicable' => true,
                    'svf_only' => (bool) $item[3],
                    'status' => $this->isInitialIntakeRequirement($item[0])
                        ? 'requested'
                        : 'pending',
                    'sort_order' => ($index + 1) * 10,
                ] + $metadata;
            })
            ->all();
    }

    private function requirementSoiMetadata(string $groupName, string $itemName, bool $initialRequired = false): array
    {
        $text = strtolower($groupName . ' ' . $itemName);

        if ($groupName === '1. Intake Pack') {
            return [
                'owner_type' => 'proponent',
                'visibility' => 'proponent_visible',
                'soi_section' => 'intake',
                'gate_step' => null,
                'is_required' => $initialRequired,
            ];
        }

        $isInternal = str_contains($text, 'response letter')
            || str_contains($text, 'investment committee')
            || str_contains($text, 'mancom')
            || str_contains($text, 'management committee')
            || str_contains($text, 'board')
            || str_contains($text, 'resolution')
            || str_contains($text, 'secretary certificate')
            || str_contains($text, 'secretary\'s certificate')
            || str_contains($text, 'neda')
            || str_contains($text, 'icc')
            || str_contains($text, 'jv selection')
            || str_contains($text, 'notice of award')
            || str_contains($text, 'noa')
            || str_contains($text, 'jva')
            || str_contains($text, 'agreement')
            || str_contains($text, 'contract')
            || str_contains($text, 'fund release')
            || str_contains($text, 'receipt')
            || str_contains($text, 'monitoring')
            || str_contains($text, 'milestone')
            || str_contains($text, 'divestment')
            || str_contains($text, 'transfer')
            || str_contains($text, 'construction')
            || str_contains($text, 'ded ')
            || str_contains($text, 'procurement')
            || str_contains($text, 'materials requisition')
            || str_contains($text, 'bidding')
            || str_contains($text, 'turn-over')
            || str_contains($text, 'turnover');

        $soiSection = match (true) {
            str_contains($text, 'divest') || str_contains($text, 'transfer') => 'divestment',
            str_contains($text, 'monitor') || str_contains($text, 'milestone') || str_contains($text, 'coa') => 'implementation_monitoring',
            str_contains($text, 'post-investment') || str_contains($text, 'post investment') => 'post_investment_strategy',
            str_contains($text, 'agreement') || str_contains($text, 'contract') || str_contains($text, 'fund') || str_contains($text, 'receipt') || str_contains($text, 'jva') || str_contains($text, 'construction') || str_contains($text, 'ded ') => 'agreement_fund_release',
            str_contains($text, 'board') || str_contains($text, 'resolution') || str_contains($text, 'secretary certificate') || str_contains($text, 'secretary\'s certificate') => 'board_approval',
            str_contains($text, 'mancom') || str_contains($text, 'management committee') || str_contains($text, 'workgroup') || str_contains($text, 'recommendation') || str_contains($text, 'presentation') => 'management_review',
            str_contains($text, 'neda') || str_contains($text, 'icc') || str_contains($text, 'due diligence') || str_contains($text, 'evaluation') || str_contains($text, 'study') || str_contains($text, 'financial model') || str_contains($text, 'risk') => 'due_diligence',
            str_contains($text, 'requirement') || str_contains($text, 'checklist') || str_contains($text, 'response letter') || str_contains($text, 'legal') || str_contains($text, 'financial') || str_contains($text, 'sec ') || str_contains($text, 'dti') => 'requirements',
            default => 'intake',
        };

        $gateStep = match (true) {
            str_contains($text, 'divest') || str_contains($text, 'transfer') => 'divestment',
            str_contains($text, 'neda') || str_contains($text, 'icc') || str_contains($text, 'jv selection') || str_contains($text, 'notice of award') || str_contains($text, 'noa') => 'jv',
            str_contains($text, 'agreement') || str_contains($text, 'contract') || str_contains($text, 'fund') || str_contains($text, 'receipt') || str_contains($text, 'jva') || str_contains($text, 'construction') || str_contains($text, 'ded ') => 'fund_release',
            str_contains($text, 'board') || str_contains($text, 'resolution') || str_contains($text, 'secretary certificate') || str_contains($text, 'secretary\'s certificate') => 'board',
            str_contains($text, 'mancom') || str_contains($text, 'management committee') || str_contains($text, 'recommendation') || str_contains($text, 'presentation') => 'mancom',
            str_contains($text, 'monitor') || str_contains($text, 'milestone') || str_contains($text, 'coa') => 'monitoring',
            default => null,
        };

        return [
            'owner_type' => $isInternal ? 'internal' : 'proponent',
            'visibility' => $isInternal ? 'internal_only' : 'proponent_visible',
            'soi_section' => $soiSection,
            'gate_step' => $isInternal ? $gateStep : null,
            'is_required' => $isInternal || $initialRequired,
        ];
    }

    private function isInitialSubmissionRequirement(string $groupName, string $itemName): bool
    {
        if ($groupName !== '1. Intake Pack') {
            return false;
        }

        $normalized = strtolower($itemName);

        return str_contains($normalized, 'letter of intent')
            || str_contains($normalized, 'project concept')
            || str_contains($normalized, 'pitch deck')
            || str_contains($normalized, 'company profile');
    }

    private function isInitialIntakeRequirement(string $groupName): bool
    {
        return $groupName === '1. Intake Pack';
    }

    private function missingInitialProposalRequirements(Project $project)
    {
        if (!$this->requiresPackageSubmissionBeforeApproval(
            (string) ($project->process_track ?: 'bdg_investment'),
            (bool) $project->is_svf
        )) {
            return collect();
        }

        return $project->requirements()
            ->where('owner_type', 'proponent')
            ->where('visibility', 'proponent_visible')
            ->whereIn('soi_section', ['intake', 'requirements'])
            ->where('is_required', true)
            ->where('status', 'requested')
            ->with('document')
            ->get()
            ->filter(function (ProjectRequirement $requirement) {
                $document = $requirement->document;

                return !$document
                    || $document->is_deleted
                    || !in_array($document->submission_status ?: 'draft', ['draft', 'submitted'], true);
            })
            ->pluck('item_name');
    }

    private function projectActionUrl(Project $project, string $tab = 'overview', array $query = []): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://127.0.0.1:3000')), '/');
        $params = array_merge([
            'project_id' => $project->id,
            'tab' => $tab,
        ], $query);

        return "{$frontendUrl}/projects?" . http_build_query($params);
    }
}
