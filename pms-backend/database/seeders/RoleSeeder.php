<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'superadmin', 'description' => 'Super Administrator', 'is_system_role' => true],
            ['id' => 2, 'name' => 'Project Officer', 'description' => 'Manages assigned projects', 'is_system_role' => true],
            ['id' => 3, 'name' => 'Staff', 'description' => 'Staff', 'is_system_role' => true],
            ['id' => 4, 'name' => 'Supervisor', 'description' => 'Supervisor', 'is_system_role' => true],
            ['id' => 5, 'name' => 'Workgroup Head', 'description' => 'Heads a workgroup', 'is_system_role' => true],
            ['id' => 6, 'name' => 'ManCom', 'description' => 'Management Committee member', 'is_system_role' => true],
            ['id' => 7, 'name' => 'Proponent', 'description' => 'Project proponent / originator', 'is_system_role' => true],
            ['id' => 8, 'name' => 'Board', 'description' => 'Board member', 'is_system_role' => true],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                array_merge($role, ['created_at' => now()])
            );
        }
    }
}
