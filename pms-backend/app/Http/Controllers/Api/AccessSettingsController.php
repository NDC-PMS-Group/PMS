<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccessSettingsController extends Controller
{
    // ============================================
    // PERMISSIONS
    // ============================================

    /**
     * Get all permissions
     */
    public function indexPermissions()
    {
        $permissions = Permission::orderBy('resource')->orderBy('action')->get();
        return PermissionResource::collection($permissions);
    }

    /**
     * Create new permission
     */
    public function storePermission(Request $request)
    {
        $validated = $request->validate([
            'resource' => 'required|string|max:50',
            'action' => 'required|string|max:50',
            'description' => 'nullable|string',
        ]);

        // Generate permission name from resource.action
        $name = $validated['resource'] . '.' . $validated['action'];

        // Check if permission already exists
        if (Permission::where('name', $name)->exists()) {
            return response()->json([
                'message' => 'Permission already exists'
            ], 422);
        }

        $permission = Permission::create([
            'name' => $name,
            'resource' => $validated['resource'],
            'action' => $validated['action'],
            'description' => $validated['description'] ?? null,
        ]);

        return new PermissionResource($permission);
    }

    /**
     * Update permission
     */
    public function updatePermission(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'resource' => 'required|string|max:50',
            'action' => 'required|string|max:50',
            'description' => 'nullable|string',
        ]);

        // Generate new permission name
        $name = $validated['resource'] . '.' . $validated['action'];

        // Check if new name conflicts with another permission
        if ($name !== $permission->name && Permission::where('name', $name)->exists()) {
            return response()->json([
                'message' => 'Permission with this resource and action already exists'
            ], 422);
        }

        $permission->update([
            'name' => $name,
            'resource' => $validated['resource'],
            'action' => $validated['action'],
            'description' => $validated['description'] ?? null,
        ]);

        return new PermissionResource($permission);
    }

    /**
     * Delete permission
     */
    public function destroyPermission(Permission $permission)
    {
        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully'
        ]);
    }

    // ============================================
    // ROLES
    // ============================================

    /**
     * Get all roles with their permissions
     */
    public function indexRoles()
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        return RoleResource::collection($roles);
    }

    /**
     * Create new role
     */
    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
            'description' => 'nullable|string',
            'is_system_role' => 'boolean',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_system_role' => $validated['is_system_role'] ?? false,
        ]);

        return new RoleResource($role->load('permissions'));
    }

    /**
     * Update role
     */
    public function updateRole(Request $request, Role $role)
    {
        // Prevent editing system roles if needed
        if ($role->is_system_role) {
            return response()->json([
                'message' => 'Cannot edit system roles'
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => 'nullable|string',
        ]);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return new RoleResource($role->load('permissions'));
    }

    /**
     * Delete role
     */
    public function destroyRole(Role $role)
    {
        // Prevent deleting system roles
        if ($role->is_system_role) {
            return response()->json([
                'message' => 'Cannot delete system roles'
            ], 403);
        }

        // Check if role is assigned to users
        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'Cannot delete role that is assigned to users'
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully'
        ]);
    }

    // ============================================
    // ROLE PERMISSIONS
    // ============================================

    /**
     * Assign permissions to role
     */
    public function assignPermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        // Attach new permissions (won't duplicate due to unique constraint)
        $role->permissions()->syncWithoutDetaching($validated['permission_ids']);

        return new RoleResource($role->load('permissions'));
    }

    /**
     * Remove permissions from role
     */
    public function removePermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role->permissions()->detach($validated['permission_ids']);

        return new RoleResource($role->load('permissions'));
    }

    /**
     * Sync all permissions for a role (replace all)
     */
    public function syncPermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role->permissions()->sync($validated['permission_ids']);

        return new RoleResource($role->load('permissions'));
    }
}