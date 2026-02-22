<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\UserResource;
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
            'data'    => new UserResource($user->fresh()->load('defaultRole')),
        ]);
    }

    /**
     * POST /api/profile/avatar
     * Upload and store a new profile photo for the authenticated user.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|file|max:5120',
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
        ]);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * GET /api/users/{user}/projects
     * Returns all projects a user is a member of, with their role on each project.
     */
    public function userProjects(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdminOrSelf($request, $user);

        $projects = $user->projectMemberships()
            ->with(['project' => function ($query) {
                $query->select([
                    'id', 'title', 'status', 'start_date', 'end_date',
                    'created_by', 'project_officer_id', 'workgroup_head_id',
                ]);
            }])
            ->get()
            ->map(fn ($membership) => [
                'id'         => $membership->project->id,
                'title'      => $membership->project->title,
                'status'     => $membership->project->status,
                'role'       => $membership->role ?? null,
                'start_date' => $membership->project->start_date,
                'end_date'   => $membership->project->end_date,
            ]);

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
        $role = $request->user()->defaultRole?->name;

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
        $role     = $authUser->defaultRole?->name;

        $isSelf  = $authUser->id === $user->id;
        $isAdmin = in_array($role, ['superadmin', 'admin']);

        abort_unless($isSelf || $isAdmin, 403, 'Unauthorized.');
    }
}