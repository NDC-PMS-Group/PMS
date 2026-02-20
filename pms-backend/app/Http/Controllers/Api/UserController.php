<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::with('defaultRole');

        // Filter by role
        if ($request->has('role_id')) {
            $query->where('default_role_id', $request->role_id);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } else {
            $query->active();
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

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load(['defaultRole', 'projectMemberships.project']);

        return new UserResource($user);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
            unset($data['password']);
        }

        $user->update($data);

        return new UserResource($user->fresh()->load('defaultRole'));
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

        return response()->json([
            'message' => 'User deactivated successfully.'
        ], 200);
    }
}