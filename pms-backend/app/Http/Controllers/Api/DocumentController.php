<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\ProjectRequirementResource;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectRequirement;
use App\Models\Task;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    private const STATUS_DRAFT = 'draft';
    private const STATUS_SUBMITTED = 'submitted';
    private const STATUS_UPDATE_REQUESTED = 'update_requested';

    /**
     * Display a listing of documents.
     */
    public function index(Request $request)
    {
        $query = Document::with(['project', 'uploadedBy', 'submittedBy', 'updateRequestedBy']);

        if ($request->has('project_id')) {
            $project = Project::findOrFail($request->project_id);
            if (!$this->canViewProject($request->user(), $project)) {
                return response()->json(['message' => 'Unauthorized to view project documents'], 403);
            }

            $query->where('project_id', $project->id);
        } elseif (!$this->isSuperAdmin($request->user())) {
            $query->whereHas('project', fn ($projectQuery) => $this->scopeProjectsForUser($projectQuery, $request->user()));
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $query->active();

        $perPage = $request->get('per_page', 15);
        $documents = $query->paginate($perPage);

        return DocumentResource::collection($documents);
    }

    /**
     * Store a newly created document.
     */
    public function store(StoreDocumentRequest $request)
    {
        $project = Project::findOrFail($request->project_id);
        if (!$this->canEditProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to upload documents for this project'], 403);
        }

        if ($request->filled('task_id')) {
            $taskBelongsToProject = Task::where('id', $request->task_id)
                ->where('project_id', $project->id)
                ->active()
                ->exists();

            if (!$taskBelongsToProject) {
                return response()->json(['message' => 'The selected task does not belong to this project'], 422);
            }
        }

        $requirement = null;
        if ($request->filled('requirement_id')) {
            $requirement = ProjectRequirement::where('id', $request->requirement_id)
                ->where('project_id', $project->id)
                ->first();

            if (!$requirement) {
                return response()->json(['message' => 'The selected requirement does not belong to this project'], 422);
            }

            if (!$this->canAttachToRequirement($request->user(), $requirement)) {
                return response()->json([
                    'message' => 'NDC has not requested this requirement yet. Please attach files only to requested requirements.',
                ], 422);
            }
        }

        $file = $request->file('file');
        $path = $file->store('documents', 'public');

        $document = Document::create([
            'project_id' => $request->project_id,
            'task_id' => $request->task_id,
            'title' => $request->title,
            'description' => $request->description,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
            'category' => $request->category,
            'version' => 1,
            'is_public' => $request->get('is_public', false),
            'requires_approval' => $request->get('requires_approval', false),
            'submission_status' => self::STATUS_DRAFT,
            'uploaded_by' => auth()->id(),
            'uploaded_at' => now(),
        ]);

        // Create initial version
        DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'change_description' => 'Initial upload',
            'created_by' => auth()->id(),
        ]);

        if ($request->filled('requirement_id')) {
            ProjectRequirement::where('id', $request->requirement_id)
                ->where('project_id', $project->id)
                ->update([
                    'document_id' => $document->id,
                    'remarks' => trim((string) $request->description) ?: 'Draft file uploaded. Submit the file package when ready for review.',
                    'updated_at' => now(),
                ]);

            $requirement = ProjectRequirement::where('id', $request->requirement_id)
                ->where('project_id', $project->id)
                ->with(['document.uploadedBy', 'document.submittedBy', 'document.updateRequestedBy', 'receivedBy'])
                ->first();
        }

        try {
            $document->loadMissing(['project.members.user', 'project.creator', 'project.projectOfficer', 'project.workgroupHead']);
            if ($document->project) {
                $notificationService = app(NotificationService::class);
                $recipients = $notificationService->internalProjectStakeholders($document->project, $request->user());

                $notificationService->notifyUsers(
                    $recipients,
                    'document_uploaded',
                    "Draft file uploaded: {$document->title}",
                    "{$document->title} was uploaded as a draft to {$document->project->title}. It must be submitted before reviewers treat it as received.",
                    $document->project,
                    null
                );
            }
        } catch (\Throwable $notificationException) {
            \Log::warning('Document upload notification failed.', [
                'document_id' => $document->id,
                'error' => $notificationException->getMessage(),
            ]);
        }

        $document->load(['project', 'uploadedBy', 'submittedBy', 'updateRequestedBy']);

        return response()->json([
            'message' => 'Draft file uploaded. Submit it when the package is ready.',
            'document' => new DocumentResource($document),
            'requirement' => $requirement ? new ProjectRequirementResource($requirement) : null,
        ], 201);
    }

    /**
     * Display the specified document.
     */
    public function show(Document $document)
    {
        if (!$this->canViewProject(auth()->user(), $document->project)) {
            return response()->json(['message' => 'Unauthorized to view this document'], 403);
        }

        $document->load(['project', 'task', 'uploadedBy', 'submittedBy', 'updateRequestedBy', 'versions']);

        return new DocumentResource($document);
    }

    public function submit(Request $request, Document $document)
    {
        $project = $document->project;
        if (!$this->canEditProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to submit this document'], 403);
        }

        if ($document->is_deleted) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (($document->submission_status ?? self::STATUS_DRAFT) === self::STATUS_SUBMITTED) {
            return new DocumentResource($document->load(['project', 'uploadedBy', 'submittedBy', 'updateRequestedBy']));
        }

        if (!$project->approvals()->exists()) {
            $missing = $this->missingInitialSubmissionRequirements($project, collect([$document->id]));
            if ($missing->isNotEmpty()) {
                return response()->json([
                    'message' => 'Complete the initial SOI intake package before submitting.',
                    'missing_requirements' => $missing->values(),
                ], 422);
            }
        }

        $this->markDocumentSubmitted($document, $request->user());
        $document->refresh()->load(['project', 'uploadedBy', 'submittedBy', 'updateRequestedBy']);
        $requirement = $this->requirementForDocument($document);
        $approvalStarted = $this->startSoiApprovalIfNeeded($document->project, $request->user());

        $this->notifyDocumentSubmitted($document, $request->user(), $requirement);

        return response()->json([
            'message' => $approvalStarted
                ? 'Document submitted and SOI approval routing started'
                : 'Document submitted for review',
            'document' => new DocumentResource($document),
            'requirement' => $requirement ? new ProjectRequirementResource($requirement) : null,
            'approval_started' => $approvalStarted,
        ]);
    }

    public function submitDrafts(Request $request, Project $project)
    {
        if (!$this->canEditProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to submit project documents'], 403);
        }

        $documents = $project->documents()
            ->active()
            ->where('submission_status', self::STATUS_DRAFT)
            ->get();

        if (!$project->approvals()->exists()) {
            $missing = $this->missingInitialSubmissionRequirements($project, $documents->pluck('id'));
            if ($missing->isNotEmpty()) {
                return response()->json([
                    'message' => 'Complete the initial SOI intake package before submitting.',
                    'missing_requirements' => $missing->values(),
                ], 422);
            }
        }

        $documents->each(fn (Document $document) => $this->markDocumentSubmitted($document, $request->user()));
        $approvalStarted = $documents->isNotEmpty()
            ? $this->startSoiApprovalIfNeeded($project, $request->user())
            : false;

        if ($documents->isNotEmpty()) {
            $project->loadMissing(['creator', 'projectOfficer', 'workgroupHead', 'members.user']);
            try {
                $notificationService = app(NotificationService::class);
                $recipients = $this->submittedDocumentRecipients($project, $request->user());
                $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://127.0.0.1:3000')), '/');

                $notificationService->notifyUsers(
                    $recipients,
                    'document_submitted',
                    "Files submitted: {$project->project_code}",
                    "{$documents->count()} draft file(s) were submitted for NDC review.\n\n"
                        . "Project: {$project->title}\n"
                        . "Project Code: {$project->project_code}\n"
                        . "Submitted by: " . ($request->user()?->full_name ?? 'Proponent') . "\n"
                        . "Submitted at: " . now()->format('M d, Y h:i A'),
                    $project,
                    null,
                    [
                        'project_code' => $project->project_code,
                        'project_title' => $project->title,
                        'submitted_by' => $request->user()?->full_name ?? 'Proponent',
                        'submitted_at' => now()->format('M d, Y h:i A'),
                        'action_url' => "{$frontendUrl}/projects?project_id={$project->id}&tab=requirements",
                        'action_label' => 'Review Submitted Files',
                    ]
                );
            } catch (\Throwable $notificationException) {
                \Log::warning('Bulk document submission notification failed.', [
                    'project_id' => $project->id,
                    'error' => $notificationException->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => $documents->isEmpty()
                ? 'No draft files to submit'
                : ($approvalStarted
                    ? "{$documents->count()} file(s) submitted and SOI approval routing started"
                    : "{$documents->count()} file(s) submitted for review"),
            'submitted_count' => $documents->count(),
            'documents' => DocumentResource::collection($documents->load(['project', 'uploadedBy', 'submittedBy', 'updateRequestedBy'])),
            'requirements' => ProjectRequirementResource::collection(
                ProjectRequirement::query()
                    ->where('project_id', $project->id)
                    ->whereIn('document_id', $documents->pluck('id'))
                    ->with(['document.uploadedBy', 'document.submittedBy', 'document.updateRequestedBy', 'receivedBy'])
                    ->get()
            ),
            'approval_started' => $approvalStarted,
        ]);
    }

    public function requestUpdate(Request $request, Document $document)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $project = $document->project;
        if (!$this->canRequestDocumentUpdate($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to request document updates'], 403);
        }

        if ($document->is_deleted) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (($document->submission_status ?? self::STATUS_DRAFT) !== self::STATUS_SUBMITTED) {
            return response()->json(['message' => 'Only submitted documents can be returned for update'], 422);
        }

        $document->update([
            'submission_status' => self::STATUS_UPDATE_REQUESTED,
            'update_requested_by' => $request->user()?->id,
            'update_requested_at' => now(),
            'update_request_reason' => $validated['reason'],
        ]);

        ProjectRequirement::where('document_id', $document->id)
            ->update([
                'status' => 'deferred',
                'remarks' => $validated['reason'],
                'updated_at' => now(),
            ]);

        $document->refresh()->load(['project.creator', 'uploadedBy', 'submittedBy', 'updateRequestedBy']);
        $this->notifyDocumentUpdateRequested($document, $request->user(), $validated['reason']);

        return response()->json([
            'message' => 'Document update requested',
            'document' => new DocumentResource($document),
        ]);
    }

    /**
     * Download document.
     */
    public function download(Document $document)
    {
        if (!$this->canViewProject(auth()->user(), $document->project)) {
            return response()->json(['message' => 'Unauthorized to download this document'], 403);
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }

    public function view(Document $document)
    {
        if (!$this->canViewProject(auth()->user(), $document->project)) {
            return response()->json(['message' => 'Unauthorized to view this document'], 403);
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $stream = Storage::disk('public')->readStream($document->file_path);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $document->file_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($document->file_name ?: $document->title) . '"',
        ]);
    }

    /**
     * Delete document.
     */
    public function destroy(Document $document)
    {
        if (!$this->canEditProject(auth()->user(), $document->project)) {
            return response()->json(['message' => 'Unauthorized to delete this document'], 403);
        }

        $linkedRequirement = ProjectRequirement::where('document_id', $document->id)->first();
        $requirementIsConfirmed = $linkedRequirement
            && in_array((string) $linkedRequirement->status, ['approved', 'approved_with_conditions', 'waived'], true);

        if (
            ($document->submission_status ?? self::STATUS_DRAFT) === self::STATUS_SUBMITTED
            && $requirementIsConfirmed
            && !$this->isSuperAdmin(auth()->user())
        ) {
            return response()->json([
                'message' => 'Confirmed files are locked. Request an update before replacing or removing this attachment.',
            ], 423);
        }

        $document->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);

        if ($linkedRequirement) {
            $linkedRequirement->update([
                'document_id' => null,
                'status' => in_array((string) $linkedRequirement->status, ['received', 'deferred', 'for_further_evaluation'], true)
                    ? 'requested'
                    : $linkedRequirement->status,
                'remarks' => 'File removed. Please attach the latest requirement file.',
                'received_at' => null,
                'received_by' => null,
            ]);
        }

        return response()->json(['message' => 'Document deleted successfully'], 200);
    }

    private function markDocumentSubmitted(Document $document, ?User $user): void
    {
        $document->update([
            'submission_status' => self::STATUS_SUBMITTED,
            'submitted_by' => $user?->id,
            'submitted_at' => now(),
            'update_requested_by' => null,
            'update_requested_at' => null,
            'update_request_reason' => null,
        ]);

        ProjectRequirement::where('document_id', $document->id)
            ->update([
                'status' => 'received',
                'received_by' => $user?->id,
                'received_at' => now(),
                'remarks' => 'Submitted for SOI review.',
                'updated_at' => now(),
            ]);
    }

    private function missingInitialSubmissionRequirements(Project $project, $submittingDocumentIds = null)
    {
        if (!$this->requiresPackageSubmissionBeforeApproval($project)) {
            return collect();
        }

        $submittingIds = collect($submittingDocumentIds ?? [])->map(fn ($id) => (int) $id)->all();

        $requirements = $project->requirements()
            ->where('group_name', '1. Intake Pack')
            ->where('is_required', true)
            ->where('status', 'requested')
            ->with('document')
            ->get();

        if ($requirements->isEmpty()) {
            return collect();
        }

        return $requirements
            ->filter(function (ProjectRequirement $requirement) use ($submittingIds) {
                $document = $requirement->document;
                if (!$document || $document->is_deleted) {
                    return true;
                }

                if (($document->submission_status ?? self::STATUS_DRAFT) === self::STATUS_SUBMITTED) {
                    return false;
                }

                return !in_array((int) $document->id, $submittingIds, true);
            })
            ->pluck('item_name');
    }

    private function requiresPackageSubmissionBeforeApproval(Project $project): bool
    {
        return (bool) $project->is_svf
            || in_array((string) ($project->process_track ?: 'bdg_investment'), ['bdg_investment', 'spg_traditional', 'spg_jv'], true);
    }

    private function canAttachToRequirement(?User $user, ProjectRequirement $requirement): bool
    {
        if (!$user) {
            return false;
        }

        $requirement->loadMissing('project');
        $isProjectCreator = $requirement->project
            && (int) $requirement->project->created_by === (int) $user->id;
        $ownerType = (string) ($requirement->owner_type ?: 'proponent');
        $visibility = (string) ($requirement->visibility ?: 'proponent_visible');
        $isExternal = $this->isExternalProponent($user);

        if ($isExternal && ($visibility === 'internal_only' || $ownerType === 'internal')) {
            return false;
        }

        if (!$isExternal && !$this->canEditProject($user, $requirement->project)) {
            return false;
        }

        if ($isExternal && !$isProjectCreator && $ownerType !== 'shared') {
            return false;
        }

        $allowedStatuses = $isExternal
            ? ['requested', 'received', 'deferred', 'for_further_evaluation']
            : ['pending', 'requested', 'received', 'deferred', 'for_further_evaluation'];

        return in_array($requirement->status, $allowedStatuses, true);
    }

    private function startSoiApprovalIfNeeded(?Project $project, ?User $user): bool
    {
        if (!$project || $project->approvals()->exists()) {
            return false;
        }

        return (bool) ApprovalController::createInitialApprovalForProject(
            (int) $project->id,
            $project->project_type_id ? (int) $project->project_type_id : null,
            (int) $user?->id
        );
    }

    private function notifyDocumentSubmitted(Document $document, ?User $actor, ?ProjectRequirement $requirement = null): void
    {
        try {
            $document->loadMissing(['project.creator', 'project.projectOfficer', 'project.workgroupHead', 'project.members.user', 'submittedBy']);
            if (!$document->project) {
                return;
            }

            $notificationService = app(NotificationService::class);
            $project = $document->project;
            $requirementName = $requirement?->item_name ?: $document->title;
            $recipients = $this->submittedDocumentRecipients($project, $actor);
            $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://127.0.0.1:3000')), '/');
            $actionUrl = "{$frontendUrl}/projects?project_id={$project->id}&tab=requirements"
                . ($requirement ? "&requirement_id={$requirement->id}" : '');

            $notificationService->notifyUsers(
                $recipients,
                'document_submitted',
                "File submitted: {$requirementName}",
                "A file was submitted for NDC review.\n\n"
                    . "Project: {$project->title}\n"
                    . "Project Code: {$project->project_code}\n"
                    . "Requirement: {$requirementName}\n"
                    . "File: {$document->file_name}\n"
                    . "Submitted by: " . ($document->submittedBy?->full_name ?? $actor?->full_name ?? 'Proponent') . "\n"
                    . "Submitted at: " . optional($document->submitted_at)->format('M d, Y h:i A'),
                $project,
                null,
                [
                    'project_code' => $project->project_code,
                    'project_title' => $project->title,
                    'requirement_name' => $requirementName,
                    'file_name' => $document->file_name,
                    'submitted_by' => $document->submittedBy?->full_name ?? $actor?->full_name ?? 'Proponent',
                    'submitted_at' => optional($document->submitted_at)->format('M d, Y h:i A'),
                    'action_url' => $actionUrl,
                    'action_label' => 'Review Submitted File',
                ]
            );
        } catch (\Throwable $notificationException) {
            \Log::warning('Document submission notification failed.', [
                'document_id' => $document->id,
                'recipient_context' => 'submitted_document_reviewers',
                'error' => $notificationException->getMessage(),
            ]);
        }
    }

    private function requirementForDocument(Document $document): ?ProjectRequirement
    {
        return ProjectRequirement::query()
            ->where('document_id', $document->id)
            ->with(['document.uploadedBy', 'document.submittedBy', 'document.updateRequestedBy', 'receivedBy'])
            ->first();
    }

    private function submittedDocumentRecipients(Project $project, ?User $actor)
    {
        $project->loadMissing(['projectOfficer', 'workgroupHead', 'members.user']);

        $approval = ProjectApproval::query()
            ->with(['currentStep.role'])
            ->where('project_id', $project->id)
            ->whereNull('completed_at')
            ->latest('id')
            ->first();

        $recipients = collect([
            $project->projectOfficer,
            $project->workgroupHead,
        ]);

        $roleId = $approval?->currentStep?->role_id;
        if ($roleId) {
            $recipients = $recipients
                ->merge(User::active()->where('default_role_id', $roleId)->get())
                ->merge($project->members
                    ->where('role_id', $roleId)
                    ->whereNull('removed_at')
                    ->where('can_approve', true)
                    ->pluck('user'));
        }

        $recipients = $recipients->merge($project->members
            ->whereNull('removed_at')
            ->filter(fn ($member) => (bool) $member->can_approve || (bool) $member->can_edit)
            ->pluck('user'));

        return $recipients
            ->filter(fn ($user) => $user instanceof User && $user->is_active)
            ->reject(fn (User $user) => $actor && (int) $user->id === (int) $actor->id)
            ->reject(fn (User $user) => $this->isExternalProponent($user))
            ->unique('id')
            ->values();
    }

    private function notifyDocumentUpdateRequested(Document $document, ?User $actor, string $reason): void
    {
        try {
            $recipients = collect([$document->uploadedBy, $document->project?->creator])
                ->filter()
                ->unique('id')
                ->reject(fn ($user) => (int) $user->id === (int) $actor?->id)
                ->values();

            app(NotificationService::class)->notifyUsers(
                $recipients,
                'document_update_requested',
                "File update requested: {$document->title}",
                "{$document->title} needs an update before it can be accepted. Reason: {$reason}",
                $document->project,
                null
            );
        } catch (\Throwable $notificationException) {
            \Log::warning('Document update request notification failed.', [
                'document_id' => $document->id,
                'error' => $notificationException->getMessage(),
            ]);
        }
    }

    private function isSuperAdmin(?User $user): bool
    {
        return $user && ((int) $user->default_role_id === 1 || $user->hasRole('superadmin'));
    }

    private function scopeProjectsForUser($query, ?User $user)
    {
        if (!$user || $this->isSuperAdmin($user)) {
            return $query;
        }

        return $query->where(function ($projectQuery) use ($user) {
            $projectQuery
                ->where('created_by', $user->id)
                ->orWhere('project_officer_id', $user->id)
                ->orWhere('workgroup_head_id', $user->id)
                ->orWhereHas('members', fn ($memberQuery) => $memberQuery
                    ->active()
                    ->where('user_id', $user->id)
                    ->where('can_view', true));
        });
    }

    private function canViewProject(?User $user, ?Project $project): bool
    {
        if (!$user || !$project) {
            return false;
        }

        if ($this->isSuperAdmin($user) || $this->hasGlobalProjectPermission($user, ['projects.view', 'project.view', 'view_project'])) {
            return true;
        }

        return $project->created_by === $user->id
            || $project->project_officer_id === $user->id
            || $project->workgroup_head_id === $user->id
            || $project->members()
                ->active()
                ->where('user_id', $user->id)
                ->where('can_view', true)
                ->exists();
    }

    private function canEditProject(?User $user, ?Project $project): bool
    {
        if (!$user || !$project) {
            return false;
        }

        if ($this->isSuperAdmin($user) || $this->hasGlobalProjectPermission($user, ['projects.update', 'project.update', 'project.edit', 'edit_project'])) {
            return true;
        }

        return $project->created_by === $user->id
            || $project->project_officer_id === $user->id
            || $project->workgroup_head_id === $user->id
            || $project->members()
                ->active()
                ->where('user_id', $user->id)
                ->where('can_edit', true)
                ->exists();
    }

    private function canRequestDocumentUpdate(?User $user, ?Project $project): bool
    {
        if (!$user || !$project) {
            return false;
        }

        if ($this->isSuperAdmin($user) || $this->hasGlobalProjectPermission($user, ['documents.review', 'documents.update', 'projects.update', 'project.update', 'project.edit', 'edit_project'])) {
            return true;
        }

        return $project->project_officer_id === $user->id
            || $project->workgroup_head_id === $user->id
            || $project->members()
                ->active()
                ->where('user_id', $user->id)
                ->where(function ($query) {
                    $query->where('can_approve', true)
                        ->orWhere('can_edit', true);
                })
                ->exists();
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

    private function isExternalProponent(?User $user): bool
    {
        return $user && ((int) $user->default_role_id === 7 || $user->hasRole('Proponent'));
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
}
