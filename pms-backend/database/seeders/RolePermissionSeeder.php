<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = DB::table('roles')->pluck('id', 'name');
        $permissions = DB::table('permissions')->pluck('id', 'name');

        $rolePermissionMap = [
            'superadmin' => $permissions->keys()->toArray(),
            'Project Officer' => [
                'dashboard.view',
                'projects.view',
                'projects.create',
                'projects.update',
                'project_map.view',
                'tasks.view',
                'tasks.create',
                'tasks.update',
                'tasks.delete',
                'documents.view',
                'documents.create',
                'documents.update',
                'documents.delete',
                'reports.view',
                'reports.create',
                'profile.view',
                'employee_profile.view',
                'users.view',
            ],
            'Staff' => [
                'dashboard.view',
                'projects.view',
                'project_map.view',
                'tasks.view',
                'tasks.create',
                'tasks.update',
                'documents.view',
                'documents.create',
                'profile.view',
            ],
            'Supervisor' => [
                'dashboard.view',
                'projects.view',
                'projects.update',
                'project_map.view',
                'tasks.view',
                'tasks.update',
                'documents.view',
                'reports.view',
                'profile.view',
            ],
            'Workgroup Head' => [
                'dashboard.view',
                'projects.view',
                'projects.create',
                'projects.update',
                'project_map.view',
                'tasks.view',
                'tasks.create',
                'tasks.update',
                'tasks.delete',
                'documents.view',
                'documents.create',
                'documents.update',
                'reports.view',
                'reports.create',
                'profile.view',
                'employee_profile.view',
                'users.view',
            ],
            'Investment Committee' => [
                'dashboard.view',
                'projects.view',
                'project_map.view',
                'tasks.view',
                'documents.view',
                'reports.view',
                'profile.view',
            ],
            'Legal and Finance' => [
                'dashboard.view',
                'projects.view',
                'projects.update',
                'project_map.view',
                'tasks.view',
                'tasks.update',
                'documents.view',
                'documents.create',
                'documents.update',
                'reports.view',
                'profile.view',
            ],
            'ManCom' => [
                'dashboard.view',
                'projects.view',
                'project_map.view',
                'tasks.view',
                'documents.view',
                'reports.view',
                'profile.view',
            ],
            'Proponent' => [
                'dashboard.view',
                'projects.view',
                'projects.create',
                'projects.update',
                'project_map.view',
                'tasks.view',
                'tasks.update',
                'documents.view',
                'documents.create',
                'documents.update',
                'documents.delete',
                'profile.view',
            ],
            'Board' => [
                'dashboard.view',
                'projects.view',
                'project_map.view',
                'tasks.view',
                'documents.view',
                'reports.view',
                'profile.view',
            ],
        ];

        DB::table('role_permissions')->delete();

        foreach ($rolePermissionMap as $roleName => $permissionNames) {
            $roleId = $roles[$roleName] ?? null;
            if (!$roleId) {
                continue;
            }

            foreach ($permissionNames as $permissionName) {
                $permissionId = $permissions[$permissionName] ?? null;
                if (!$permissionId) {
                    continue;
                }

                DB::table('role_permissions')->updateOrInsert(
                    [
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ],
                    [
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
