<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'superadmin', 'description' => 'Full system administrator', 'is_system_role' => true],
            ['id' => 2, 'name' => 'Project Officer', 'description' => 'Handles intake, evaluation, due diligence, and project monitoring', 'is_system_role' => true],
            ['id' => 3, 'name' => 'Staff', 'description' => 'Works on assigned project tasks and records', 'is_system_role' => true],
            ['id' => 4, 'name' => 'Supervisor', 'description' => 'Monitors assigned projects and operational task progress', 'is_system_role' => true],
            ['id' => 5, 'name' => 'Workgroup Head', 'description' => 'Reviews workgroup recommendations and approval routing', 'is_system_role' => true],
            ['id' => 6, 'name' => 'ManCom', 'description' => 'Management Committee reviewer and approver', 'is_system_role' => true],
            ['id' => 7, 'name' => 'Proponent', 'description' => 'External company or proponent that submits proposals and requirements', 'is_system_role' => true],
            ['id' => 8, 'name' => 'Board', 'description' => 'Board reviewer and approver', 'is_system_role' => true],
            ['id' => 9, 'name' => 'Investment Committee', 'description' => 'Reviews investment evaluation outputs and recommendations', 'is_system_role' => true],
            ['id' => 10, 'name' => 'Legal and Finance', 'description' => 'Reviews legal, finance, compliance, agreement, and fund-release items', 'is_system_role' => true],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                array_merge($role, ['created_at' => now()])
            );
        }
    }
}
