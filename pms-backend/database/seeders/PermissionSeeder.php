<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'dashboard.view', 'resource' => 'dashboard', 'action' => 'view', 'description' => 'Dashboard'],
            ['name' => 'access_settings.view', 'resource' => 'access_settings', 'action' => 'view', 'description' => 'Access Settings'],
            ['name' => 'admin_tools.view', 'resource' => 'admin_tools', 'action' => 'view', 'description' => 'Admin Tools'],
            ['name' => 'access_settings.create', 'resource' => 'access_settings', 'action' => 'create', 'description' => 'Access Settings'],
            ['name' => 'access_settings.update', 'resource' => 'access_settings', 'action' => 'update', 'description' => 'Access Settings'],
            ['name' => 'access_settings.delete', 'resource' => 'access_settings', 'action' => 'delete', 'description' => 'Access Settings'],
            ['name' => 'activity_logs.view', 'resource' => 'activity_logs', 'action' => 'view', 'description' => 'Activity Logs'],
            ['name' => 'activity_logs.create', 'resource' => 'activity_logs', 'action' => 'create', 'description' => 'Activity Logs'],
            ['name' => 'activity_logs.update', 'resource' => 'activity_logs', 'action' => 'update', 'description' => 'Activity Logs'],
            ['name' => 'activity_logs.delete', 'resource' => 'activity_logs', 'action' => 'delete', 'description' => 'Activity Logs'],
            ['name' => 'organization.view', 'resource' => 'organization', 'action' => 'view', 'description' => 'Organization'],
            ['name' => 'organization.create', 'resource' => 'organization', 'action' => 'create', 'description' => 'Organization'],
            ['name' => 'organization.update', 'resource' => 'organization', 'action' => 'update', 'description' => 'Organization'],
            ['name' => 'organization.delete', 'resource' => 'organization', 'action' => 'delete', 'description' => 'Organization'],
            ['name' => 'system_settings.view', 'resource' => 'system_settings', 'action' => 'view', 'description' => 'System Settings'],
            ['name' => 'system_settings.create', 'resource' => 'system_settings', 'action' => 'create', 'description' => 'System Settings'],
            ['name' => 'system_settings.update', 'resource' => 'system_settings', 'action' => 'update', 'description' => 'System Settings'],
            ['name' => 'system_settings.delete', 'resource' => 'system_settings', 'action' => 'delete', 'description' => 'System Settings'],
            ['name' => 'users.view', 'resource' => 'users', 'action' => 'view', 'description' => 'Users'],
            ['name' => 'users.create', 'resource' => 'users', 'action' => 'create', 'description' => 'Users'],
            ['name' => 'users.update', 'resource' => 'users', 'action' => 'update', 'description' => 'Users'],
            ['name' => 'users.delete', 'resource' => 'users', 'action' => 'delete', 'description' => 'Users'],
            ['name' => 'projects.view', 'resource' => 'projects', 'action' => 'view', 'description' => null],
            ['name' => 'projects.create', 'resource' => 'projects', 'action' => 'create', 'description' => null],
            ['name' => 'projects.update', 'resource' => 'projects', 'action' => 'update', 'description' => null],
            ['name' => 'projects.delete', 'resource' => 'projects', 'action' => 'delete', 'description' => null],
            ['name' => 'tasks.view', 'resource' => 'tasks', 'action' => 'view', 'description' => 'Tasks'],
            ['name' => 'tasks.create', 'resource' => 'tasks', 'action' => 'create', 'description' => 'Tasks'],
            ['name' => 'tasks.update', 'resource' => 'tasks', 'action' => 'update', 'description' => 'Tasks'],
            ['name' => 'tasks.delete', 'resource' => 'tasks', 'action' => 'delete', 'description' => 'Tasks'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                array_merge($permission, ['created_at' => now()])
            );
        }
    }
}
