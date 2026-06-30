<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    // =========================================================================
    // OWN PROFILE
    // =========================================================================

    /**
     * GET /api/profile
     * Returns the authenticated user's full profile with relationships.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'defaultRole.permissions',
            'projectMemberships.project',
            'assignedTasks',
            'registrationDocuments',
            'previousProjects',
            'receivedInvitations.project',
            'receivedInvitations.invitedBy',
        ]);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * PUT /api/profile
     * Update the authenticated user's own profile info.
     * Role, employee_id, and is_active are NOT editable here — admin-only fields.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update($request->validated());

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data'    => new UserResource($user->fresh()->load(['defaultRole', 'registrationDocuments'])),
        ]);
    }

    /**
     * POST /api/profile/avatar
     * Upload and store a new profile photo for the authenticated user.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar from storage if it was a local file
        if ($user->profile_photo_url && str_starts_with($user->profile_photo_url, 'avatars/')) {
            Storage::disk('public')->delete($user->profile_photo_url);
        }

        // Store new avatar under public disk → storage/app/public/avatars/{userId}/
        $path = $request->file('avatar')->store("avatars/{$user->id}", 'public');

        $user->update(['profile_photo_url' => $path]);

        return response()->json([
            'message'     => 'Avatar uploaded successfully.',
            'profile_photo' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * PUT /api/profile/password
     * Change the authenticated user's password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify old password against password_hash column
        if (!Hash::check($request->current_password, $user->password_hash)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors'  => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        $user->update([
            'password_hash' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    // =========================================================================
    // ADMIN — VIEW ANOTHER USER'S PROFILE
    // =========================================================================

    /**
     * GET /api/users/{user}/profile
     * Admin view of any user's full profile.
     */
    public function showUser(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $user->load([
            'defaultRole.permissions',
            'projectMemberships.project',
            'assignedTasks',
            'registrationDocuments',
            'previousProjects',
        ]);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * GET /api/users/{user}/projects
     * Returns projects visible to the profile user based on PMS ownership and access.
     */
    public function userProjects(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdminOrSelf($request, $user);

        $projects = Project::query()
            ->with([
                'status',
                'currentStage',
                'members' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->whereNull('removed_at')
                    ->with('role'),
            ])
            ->visibleDraftsTo($request->user())
            ->where('is_deleted', false)
            ->where(function ($scope) use ($user) {
                $scope->where('created_by', $user->id)
                    ->orWhere('project_officer_id', $user->id)
                    ->orWhere('workgroup_head_id', $user->id)
                    ->orWhere('proponent_email', $user->email)
                    ->orWhereHas('members', function ($memberQuery) use ($user) {
                        $memberQuery->where('user_id', $user->id)
                            ->whereNull('removed_at')
                            ->where('can_view', true);
                    })
                    ->orWhereHas('tasks', function ($taskQuery) use ($user) {
                        $taskQuery->where('assigned_to', $user->id);
                    });

                foreach ($this->proponentSearchNames($user) as $name) {
                    $scope->orWhere('proponent_name', 'like', "%{$name}%");
                }
            })
            ->latest('updated_at')
            ->get()
            ->map(fn (Project $project) => [
                'id'         => $project->id,
                'project_code' => $project->project_code,
                'title'      => $project->title,
                'status'     => $project->status?->name ?? 'No Status',
                'stage'      => $project->currentStage?->name,
                'role'       => $this->profileProjectRole($project, $user),
                'source'     => 'system',
                'start_date' => $project->start_date?->toDateString(),
                'end_date'   => ($project->actual_completion_date ?? $project->target_completion_date)?->toDateString(),
                'monitoring_status' => $project->monitoring_status,
                'monitoring_submission_status' => $project->monitoring_submission_status,
                'monitoring_submitted_at' => $project->monitoring_submitted_at?->toDateTimeString(),
                'monitoring_reviewed_at' => $project->monitoring_reviewed_at?->toDateTimeString(),
                'monitoring_review_notes' => $project->monitoring_review_notes,
                'monitoring_metrics' => $project->financial_metrics ?? [],
            ]);

        $projects = $projects
            ->concat($this->declaredPreviousProjects($user))
            ->values();

        return response()->json([
            'data' => $projects,
        ]);
    }

    /**
     * GET /api/users/{user}/tasks
     * Returns all tasks assigned to the user, with basic project context.
     */
    public function userTasks(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdminOrSelf($request, $user);

        $status = $request->query('status');
        $limit  = (int) $request->query('limit', 20);

        $tasks = $user->assignedTasks()
            ->with(['project:id,title'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($task) => [
                'id'          => $task->id,
                'title'       => $task->title,
                'status'      => $task->status,
                'priority'    => $task->priority ?? null,
                'due_date'    => $task->due_date ?? null,
                'progress'    => $task->progress ?? 0,
                'project'     => $task->project ? [
                    'id'    => $task->project->id,
                    'title' => $task->project->title,
                ] : null,
            ]);

        return response()->json([
            'data' => $tasks,
        ]);
    }

    /**
     * GET /api/users/{user}/activity
     * Returns audit log entries attributed to the user (recent activity feed).
     */
    public function userActivity(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdminOrSelf($request, $user);

        $limit = (int) $request->query('limit', 15);

        $activity = $user->auditLogs()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($log) => [
                'id'          => $log->id,
                'action'      => $log->action,
                'description' => $log->description ?? null,
                'model_type'  => $log->model_type ?? null,
                'model_id'    => $log->model_id ?? null,
                'created_at'  => $log->created_at->toDateTimeString(),
            ]);

        return response()->json([
            'data' => $activity,
        ]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Ensure the requester is a superadmin or admin.
     */
    private function authorizeAdmin(Request $request): void
    {
        $role = strtolower((string) $request->user()->defaultRole?->name);

        abort_unless(
            in_array($role, ['superadmin', 'admin']),
            403,
            'Unauthorized.'
        );
    }

    /**
     * Allow access if requester is admin OR is viewing their own data.
     */
    private function authorizeAdminOrSelf(Request $request, User $user): void
    {
        $authUser = $request->user();
        $role     = strtolower((string) $authUser->defaultRole?->name);

        $isSelf  = $authUser->id === $user->id;
        $isAdmin = in_array($role, ['superadmin', 'admin']);

        abort_unless($isSelf || $isAdmin, 403, 'Unauthorized.');
    }

    private function profileProjectRole(Project $project, User $user): string
    {
        if ((int) $project->created_by === (int) $user->id) {
            return 'Creator';
        }

        if ((int) $project->project_officer_id === (int) $user->id) {
            return 'Project Officer';
        }

        if ((int) $project->workgroup_head_id === (int) $user->id) {
            return 'Workgroup Head';
        }

        $member = $project->members->first();
        if ($member) {
            return $member->assignment_type
                ? str_replace('_', ' ', $member->assignment_type)
                : ($member->role?->name ?? 'Team Member');
        }

        if ($project->proponent_email === $user->email || $this->projectMatchesProponentName($project, $user)) {
            return 'Proponent';
        }

        return 'Assigned Task';
    }

    private function proponentSearchNames(User $user): array
    {
        return collect([
            $user->organization_name,
            trim("{$user->first_name} {$user->last_name}"),
            $user->full_name,
        ])
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn ($name) => trim($name))
            ->unique()
            ->values()
            ->all();
    }

    private function projectMatchesProponentName(Project $project, User $user): bool
    {
        $projectName = strtolower((string) $project->proponent_name);

        if ($projectName === '') {
            return false;
        }

        foreach ($this->proponentSearchNames($user) as $name) {
            if (str_contains($projectName, strtolower($name))) {
                return true;
            }
        }

        return false;
    }

    private function declaredPreviousProjects(User $user)
    {
        $dbProjects = $user->previousProjects()->get()->map(fn ($project) => [
            'id' => "db-{$project->id}",
            'project_code' => 'TRACK RECORD',
            'title' => $project->title,
            'description' => $project->description,
            'client_partner' => $project->client_partner,
            'project_value' => $project->project_value,
            'status' => $project->status ?? 'Completed',
            'stage' => 'Project Experience',
            'role' => 'Proponent',
            'source' => 'declared_db',
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'monitoring_status' => null,
            'monitoring_submission_status' => null,
            'monitoring_submitted_at' => null,
            'monitoring_reviewed_at' => null,
            'monitoring_review_notes' => null,
            'monitoring_metrics' => [],
        ]);

        $profile = (array) ($user->proponent_profile ?? []);
        $raw = trim((string) ($profile['previous_projects'] ?? ''));

        if ($raw === '') {
            return $dbProjects;
        }

        $flatProjects = collect(preg_split('/\\r\\n|\\r|\\n|;/', $raw))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->map(fn ($item, $index) => [
                'id' => "declared-{$user->id}-{$index}",
                'project_code' => 'TRACK RECORD',
                'title' => $item,
                'description' => $item,
                'status' => 'Company Record',
                'stage' => 'Project Experience',
                'role' => 'Proponent',
                'source' => 'declared',
                'start_date' => null,
                'end_date' => null,
                'monitoring_status' => null,
                'monitoring_submission_status' => null,
                'monitoring_submitted_at' => null,
                'monitoring_reviewed_at' => null,
                'monitoring_review_notes' => null,
                'monitoring_metrics' => [],
            ]);

        return $dbProjects->concat($flatProjects);
    }

    /**
     * GET /api/profile/previous-projects
     */
    public function listPreviousProjects(Request $request): JsonResponse
    {
        $projects = $request->user()->previousProjects()->orderBy('start_date', 'desc')->get();
        return response()->json($projects);
    }

    /**
     * POST /api/profile/previous-projects
     */
    public function storePreviousProject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_partner' => 'nullable|string|max:255',
            'project_value' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|string|max:255',
        ]);

        $project = $request->user()->previousProjects()->create($validated);

        return response()->json([
            'message' => 'Previous project added successfully.',
            'data' => $project
        ], 201);
    }

    /**
     * PUT /api/profile/previous-projects/{id}
     */
    public function updatePreviousProject(Request $request, $id): JsonResponse
    {
        $project = $request->user()->previousProjects()->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_partner' => 'nullable|string|max:255',
            'project_value' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|string|max:255',
        ]);

        $project->update($validated);

        return response()->json([
            'message' => 'Previous project updated successfully.',
            'data' => $project
        ]);
    }

    /**
     * DELETE /api/profile/previous-projects/{id}
     */
    public function deletePreviousProject(Request $request, $id): JsonResponse
    {
        $project = $request->user()->previousProjects()->findOrFail($id);
        $project->delete();

        return response()->json([
            'message' => 'Previous project deleted successfully.'
        ]);
    }
}
