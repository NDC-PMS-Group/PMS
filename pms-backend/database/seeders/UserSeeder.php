<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Keep these defaults explicit so local and Docker logins are predictable.
        $defaultPassword = env('SEED_DEFAULT_PASSWORD', 'Password123!');
        $passwordHash = Hash::make($defaultPassword);

        $users = [
            [
                'id' => 2,
                'username' => 'superadmin',
                'email' => 'sa@gmail.com',
                'password_hash' => $passwordHash,
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'default_role_id' => 1,
                'is_active' => true,
                'created_at' => '2026-02-01 14:05:22',
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'username' => 'admin',
                'email' => 'admin@ndc.gov.ph',
                'password_hash' => $passwordHash,
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'default_role_id' => 2,
                'is_active' => true,
                'created_at' => '2026-02-03 02:14:39',
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'username' => 'testuser',
                'email' => 'testuser@ndc.gov.ph',
                'password_hash' => $passwordHash,
                'first_name' => 'Test',
                'last_name' => 'User',
                'default_role_id' => 2,
                'is_active' => true,
                'created_at' => '2026-02-03 02:14:47',
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'username' => 'proponent',
                'email' => 'proponent@ndc.gov.ph',
                'password_hash' => $passwordHash,
                'first_name' => 'External',
                'last_name' => 'Proponent',
                'phone_number' => '+63 917 555 0100',
                'address' => 'Makati City, Metro Manila',
                'organization_name' => 'Sample Proponent Corporation',
                'organization_type' => 'Private Company',
                'organization_registration_no' => 'SEC-2026-0001',
                'department' => 'Sample Proponent Corporation',
                'position' => 'External Proponent Representative',
                'default_role_id' => 7,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['id' => $user['id']],
                $user
            );
        }
    }
}
