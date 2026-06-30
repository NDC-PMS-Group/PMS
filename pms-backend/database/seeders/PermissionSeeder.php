<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'dashboard.view', 'resource' => 'dashboard', 'action' => 'view', 'description' => 'View dashboard analytics, KPIs, and pending actions'],
            ['name' => 'projects.view', 'resource' => 'projects', 'action' => 'view', 'description' => 'View project proposals, SOI flow, requirements, files, teams, and records'],
            ['name' => 'projects.create', 'resource' => 'projects', 'action' => 'create', 'description' => 'Create internal or external project proposals'],
            ['name' => 'projects.update', 'resource' => 'projects', 'action' => 'update', 'description' => 'Update editable project details and workflow records'],
            ['name' => 'projects.delete', 'resource' => 'projects', 'action' => 'delete', 'description' => 'Delete project records when policy allows'],
            ['name' => 'project_map.view', 'resource' => 'project_map', 'action' => 'view', 'description' => 'View project map, coordinates, and location thumbnails'],
            ['name' => 'tasks.view', 'resource' => 'tasks', 'action' => 'view', 'description' => 'View work-plan tasks, subtasks, and history'],
            ['name' => 'tasks.create', 'resource' => 'tasks', 'action' => 'create', 'description' => 'Create project work-plan tasks and subtasks'],
            ['name' => 'tasks.update', 'resource' => 'tasks', 'action' => 'update', 'description' => 'Update task status, progress, priority, and assignee'],
            ['name' => 'tasks.delete', 'resource' => 'tasks', 'action' => 'delete', 'description' => 'Delete tasks or subtasks when policy allows'],
            ['name' => 'documents.view', 'resource' => 'documents', 'action' => 'view', 'description' => 'View project files and submitted requirements'],
            ['name' => 'documents.create', 'resource' => 'documents', 'action' => 'create', 'description' => 'Upload draft project files and requirements'],
            ['name' => 'documents.update', 'resource' => 'documents', 'action' => 'update', 'description' => 'Submit drafts or request file updates'],
            ['name' => 'documents.delete', 'resource' => 'documents', 'action' => 'delete', 'description' => 'Delete unsubmitted draft files when policy allows'],
            ['name' => 'reports.view', 'resource' => 'reports', 'action' => 'view', 'description' => 'View project, task, financial, and GCG reports'],
            ['name' => 'reports.create', 'resource' => 'reports', 'action' => 'create', 'description' => 'Create saved reports or export filtered project lists'],
            ['name' => 'profile.view', 'resource' => 'profile', 'action' => 'view', 'description' => 'View and maintain own profile and proponent details'],
            ['name' => 'employee_profile.view', 'resource' => 'employee_profile', 'action' => 'view', 'description' => 'View another user or proponent profile for review'],
            ['name' => 'organization.view', 'resource' => 'organization', 'action' => 'view', 'description' => 'View users, departments, account approvals, and proponent records'],
            ['name' => 'organization.create', 'resource' => 'organization', 'action' => 'create', 'description' => 'Create organization records and internal users'],
            ['name' => 'organization.update', 'resource' => 'organization', 'action' => 'update', 'description' => 'Approve accounts, update users, roles, and organization data'],
            ['name' => 'organization.delete', 'resource' => 'organization', 'action' => 'delete', 'description' => 'Delete organization or user records when policy allows'],
            ['name' => 'users.view', 'resource' => 'users', 'action' => 'view', 'description' => 'View user records for assignment and routing'],
            ['name' => 'users.create', 'resource' => 'users', 'action' => 'create', 'description' => 'Create user accounts'],
            ['name' => 'users.update', 'resource' => 'users', 'action' => 'update', 'description' => 'Update user accounts and role assignment'],
            ['name' => 'users.delete', 'resource' => 'users', 'action' => 'delete', 'description' => 'Delete user accounts when policy allows'],
            ['name' => 'admin_tools.view', 'resource' => 'admin_tools', 'action' => 'view', 'description' => 'Show the Admin Tools navigation group'],
            ['name' => 'access_settings.view', 'resource' => 'access_settings', 'action' => 'view', 'description' => 'View role and permission settings'],
            ['name' => 'access_settings.create', 'resource' => 'access_settings', 'action' => 'create', 'description' => 'Create roles and permission modules'],
            ['name' => 'access_settings.update', 'resource' => 'access_settings', 'action' => 'update', 'description' => 'Update role permission assignment'],
            ['name' => 'access_settings.delete', 'resource' => 'access_settings', 'action' => 'delete', 'description' => 'Delete custom roles or permission modules'],
            ['name' => 'system_settings.view', 'resource' => 'system_settings', 'action' => 'view', 'description' => 'View application configuration'],
            ['name' => 'system_settings.create', 'resource' => 'system_settings', 'action' => 'create', 'description' => 'Create application configuration entries'],
            ['name' => 'system_settings.update', 'resource' => 'system_settings', 'action' => 'update', 'description' => 'Update application configuration'],
            ['name' => 'system_settings.delete', 'resource' => 'system_settings', 'action' => 'delete', 'description' => 'Delete application configuration entries'],
            ['name' => 'activity_logs.view', 'resource' => 'activity_logs', 'action' => 'view', 'description' => 'View audit trails and system activity'],
            ['name' => 'activity_logs.create', 'resource' => 'activity_logs', 'action' => 'create', 'description' => 'Create activity log entries'],
            ['name' => 'activity_logs.update', 'resource' => 'activity_logs', 'action' => 'update', 'description' => 'Update activity log entries'],
            ['name' => 'activity_logs.delete', 'resource' => 'activity_logs', 'action' => 'delete', 'description' => 'Delete activity log entries'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                array_merge($permission, ['created_at' => now()])
            );
        }
    }
}
