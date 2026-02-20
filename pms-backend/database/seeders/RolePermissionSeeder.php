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
                'tasks.view',
                'tasks.create',
                'tasks.update',
                'users.view',
            ],
            'Staff' => [
                'dashboard.view',
                'projects.view',
                'tasks.view',
                'tasks.create',
                'tasks.update',
            ],
            'Supervisor' => [
                'dashboard.view',
                'projects.view',
                'projects.update',
                'tasks.view',
                'tasks.update',
            ],
            'Workgroup Head' => [
                'dashboard.view',
                'projects.view',
                'projects.create',
                'projects.update',
                'tasks.view',
                'tasks.create',
                'tasks.update',
                'tasks.delete',
                'users.view',
            ],
            'ManCom' => [
                'dashboard.view',
                'projects.view',
                'tasks.view',
            ],
            'Proponent' => [
                'dashboard.view',
                'projects.view',
                'projects.create',
                'tasks.view',
            ],
            'Board' => [
                'dashboard.view',
                'projects.view',
                'tasks.view',
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
