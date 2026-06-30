<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\ProponentRegistrationDocument;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::with(['defaultRole', 'registrationDocuments']);

        // Filter by role
        if ($request->has('role_id')) {
            $query->where('default_role_id', $request->role_id);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by department
        if ($request->has('department')) {
            $query->where('department', $request->department);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowedSorts = ['first_name', 'last_name', 'email', 'username', 'department', 'position', 'created_at', 'last_login'];
        
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request)
    {
        $user = User::create([
            // Identity
            'username'          => $request->username,
            'email'             => $request->email,
            'password_hash'     => Hash::make($request->password),

            // Name
            'first_name'        => $request->first_name,
            'middle_name'       => $request->middle_name,
            'last_name'         => $request->last_name,
            'suffix'            => $request->suffix,

            // Contact & Address
            'phone_number'      => $request->phone_number,
            'address'           => $request->address,
            'organization_name' => $request->organization_name,
            'organization_type' => $request->organization_type,
            'organization_registration_no' => $request->organization_registration_no,
            'proponent_profile' => $request->input('proponent_profile', []),

            // Profile
            'profile_photo_url' => $request->profile_photo_url,

            // Employment
            'employee_id'       => $request->employee_id,
            'department'        => $request->department,
            'position'          => $request->position,
            'date_hired'        => $request->date_hired,
            'birth_date'        => $request->birth_date,

            // Access
            'default_role_id'   => $request->default_role_id,
            'is_active'         => $request->get('is_active', true),
        ]);

        return (new UserResource($user->load('defaultRole')))
            ->response()
            ->setStatusCode(201);
    }

    public function inviteStaff(Request $request)
    {
        $actor = $request->user();
        if (!$actor || !((int) $actor->default_role_id === 1 || $actor->hasPermissionTo('organization.create') || $actor->hasPermissionTo('users.create'))) {
            return response()->json(['message' => 'Unauthorized to invite staff accounts'], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:users,email',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'suffix' => 'nullable|string|max:20',
            'default_role_id' => 'required|exists:roles,id',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'phone_number' => 'nullable|string|max:20',
        ]);

        $role = \App\Models\Role::findOrFail($validated['default_role_id']);
        if (strtolower($role->name) === 'proponent') {
            return response()->json(['message' => 'Use public proponent registration for external proponents.'], 422);
        }

        $token = Str::random(48);
        $user = User::create([
            'username' => strtolower(strtok($validated['email'], '@')) . '-' . substr(md5($validated['email']), 0, 6),
            'email' => $validated['email'],
            'password_hash' => Hash::make(Str::random(32)),
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'suffix' => $validated['suffix'] ?? null,
            'phone_number' => $validated['phone_number'] ?? null,
            'department' => $validated['department'] ?? null,
            'position' => $validated['position'] ?? null,
            'default_role_id' => $validated['default_role_id'],
            'is_active' => false,
            'staff_invitation_token' => hash('sha256', $token),
            'staff_invitation_expires_at' => now()->addDays(7),
            'invited_by_id' => $actor->id,
        ]);

        $inviteUrl = rtrim(config('app.frontend_url', config('app.url')), '/') . '/staff-invite/' . $token;

        return response()->json([
            'message' => 'Staff invitation created. Share the setup link with the invited account holder.',
            'invite_url' => $inviteUrl,
            'user' => new UserResource($user->load('defaultRole', 'invitedBy')),
        ], 201);
    }

    public function acceptStaffInvitation(Request $request, string $token)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $hashedToken = hash('sha256', $token);
        $user = User::with('defaultRole')
            ->where('staff_invitation_token', $hashedToken)
            ->whereNull('staff_invitation_accepted_at')
            ->first();

        if (!$user || !$user->staff_invitation_expires_at || now()->greaterThan($user->staff_invitation_expires_at)) {
            return response()->json(['message' => 'This staff invitation is invalid or expired.'], 422);
        }

        $user->update([
            'password_hash' => Hash::make($request->password),
            'is_active' => true,
            'email_verified_at' => now(),
            'staff_invitation_token' => null,
            'staff_invitation_accepted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Account setup complete. You may now sign in.',
            'user' => new UserResource($user->fresh()->load('defaultRole')),
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load(['defaultRole', 'projectMemberships.project', 'registrationDocuments']);

        return new UserResource($user);
    }

    public function viewRegistrationDocument(Request $request, User $user, ProponentRegistrationDocument $document)
    {
        if (!$this->canAccessRegistrationDocument($request->user(), $user, $document)) {
            return response()->json(['message' => 'Unauthorized to view this registration document'], 403);
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

    public function downloadRegistrationDocument(Request $request, User $user, ProponentRegistrationDocument $document)
    {
        if (!$this->canAccessRegistrationDocument($request->user(), $user, $document)) {
            return response()->json(['message' => 'Unauthorized to download this registration document'], 403);
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();
        $wasActive = (bool) $user->is_active;

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
            unset($data['password']);
        }

        $user->update($data);
        $freshUser = $user->fresh()->load('defaultRole');

        if (array_key_exists('is_active', $data) && $wasActive !== (bool) $freshUser->is_active) {
            try {
                $approved = (bool) $freshUser->is_active;
                app(NotificationService::class)->notifyUser(
                    $freshUser,
                    $approved ? 'account_approved' : 'account_deactivated',
                    $approved ? 'NDC account approved' : 'NDC account deactivated',
                    $approved
                        ? 'Your proponent account was approved. You may now sign in and submit proposals.'
                        : 'Your NDC PMS account was deactivated. Contact an NDC administrator if you need assistance.',
                    $freshUser
                );
            } catch (\Throwable $exception) {
                Log::warning('Account status notification failed.', [
                    'user_id' => $freshUser->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return new UserResource($freshUser);
    }

    /**
     * Deactivate the specified user (soft delete).
     */
    public function destroy(User $user)
    {
        // Prevent deactivating yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.'
            ], 403);
        }

        $user->update(['is_active' => false]);

        try {
            app(NotificationService::class)->notifyUser(
                $user->fresh(),
                'account_deactivated',
                'NDC account deactivated',
                'Your NDC PMS account was deactivated. Contact an NDC administrator if you need assistance.',
                $user
            );
        } catch (\Throwable $exception) {
            Log::warning('Account deactivation notification failed.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'User deactivated successfully.'
        ], 200);
    }

    private function canAccessRegistrationDocument(?User $actor, User $user, ProponentRegistrationDocument $document): bool
    {
        if (!$actor || $document->user_id !== $user->id) {
            return false;
        }

        if ($actor->id === $user->id) {
            return true;
        }

        return in_array((int) $actor->default_role_id, [1, 2], true)
            || $actor->hasPermissionTo('organization.view')
            || $actor->hasPermissionTo('organization.update');
    }
}
