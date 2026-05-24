<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\Task;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Display a listing of documents.
     */
    public function index(Request $request)
    {
        $query = Document::with(['project', 'uploadedBy']);

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

        if ($request->filled('requirement_id')) {
            $requirementBelongsToProject = ProjectRequirement::where('id', $request->requirement_id)
                ->where('project_id', $project->id)
                ->exists();

            if (!$requirementBelongsToProject) {
                return response()->json(['message' => 'The selected requirement does not belong to this project'], 422);
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
                    'status' => 'received',
                    'received_by' => auth()->id(),
                    'received_at' => now(),
                    'remarks' => $request->description,
                    'updated_at' => now(),
                ]);
        }

        try {
            $document->loadMissing(['project.members.user', 'project.creator', 'project.projectOfficer', 'project.workgroupHead']);
            if ($document->project) {
                $notificationService = app(NotificationService::class);
                $recipients = $notificationService->projectStakeholders($document->project)
                    ->reject(fn ($user) => (int) $user->id === (int) auth()->id())
                    ->values();

                $notificationService->notifyUsers(
                    $recipients,
                    'document_uploaded',
                    "Document uploaded: {$document->title}",
                    "{$document->title} was uploaded to {$document->project->title}.",
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

        return new DocumentResource($document->load(['project', 'uploadedBy']));
    }

    /**
     * Display the specified document.
     */
    public function show(Document $document)
    {
        if (!$this->canViewProject(auth()->user(), $document->project)) {
            return response()->json(['message' => 'Unauthorized to view this document'], 403);
        }

        $document->load(['project', 'task', 'uploadedBy', 'versions']);

        return new DocumentResource($document);
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

    /**
     * Delete document.
     */
    public function destroy(Document $document)
    {
        if (!$this->canEditProject(auth()->user(), $document->project)) {
            return response()->json(['message' => 'Unauthorized to delete this document'], 403);
        }

        $document->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);

        return response()->json(['message' => 'Document deleted successfully'], 200);
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

        if ($this->isSuperAdmin($user) || $this->hasAnyPermission($user, ['projects.view', 'project.view', 'view_project'])) {
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

        if ($this->isSuperAdmin($user) || $this->hasAnyPermission($user, ['projects.update', 'project.update', 'project.edit', 'edit_project'])) {
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
}
