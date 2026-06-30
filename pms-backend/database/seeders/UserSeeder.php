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
                'id' => 7,
                'username' => 'pdo',
                'email' => 'pdo@ndc.gov.ph',
                'password_hash' => $passwordHash,
                'first_name' => 'Project',
                'last_name' => 'Officer',
                'default_role_id' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 8,
                'username' => 'workgrouphead',
                'email' => 'wgh@ndc.gov.ph',
                'password_hash' => $passwordHash,
                'first_name' => 'Workgroup',
                'last_name' => 'Head',
                'default_role_id' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 9,
                'username' => 'mancom',
                'email' => 'mancom@ndc.gov.ph',
                'password_hash' => $passwordHash,
                'first_name' => 'ManCom',
                'last_name' => 'Reviewer',
                'default_role_id' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 10,
                'username' => 'board',
                'email' => 'board@ndc.gov.ph',
                'password_hash' => $passwordHash,
                'first_name' => 'Board',
                'last_name' => 'Reviewer',
                'default_role_id' => 8,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 12,
                'username' => 'legalfinance',
                'email' => 'legalfinance@ndc.gov.ph',
                'password_hash' => $passwordHash,
                'first_name' => 'Legal and Finance',
                'last_name' => 'Reviewer',
                'default_role_id' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 13,
                'username' => 'investmentcommittee',
                'email' => 'ic@ndc.gov.ph',
                'password_hash' => $passwordHash,
                'first_name' => 'Investment Committee',
                'last_name' => 'Reviewer',
                'default_role_id' => 9,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'username' => 'proponent',
                'email' => 'alvindalejoyosa30@gmail.com',
                'password_hash' => $passwordHash,
                'first_name' => 'External',
                'last_name' => 'Proponent',
                'phone_number' => '+63 917 555 0100',
                'address' => 'Makati City, Metro Manila',
                'organization_name' => 'Sample Proponent Corporation',
                'organization_type' => 'Private Company',
                'organization_registration_no' => 'SEC-2026-0001',
                'proponent_profile' => json_encode([
                    'business_summary' => 'A private-sector infrastructure and technology proponent focused on NDC-aligned investments, joint ventures, and implementation partnerships.',
                    'project_experience' => 'Experienced in preparing LOI packages, financial assumptions, site data, and implementation plans for public-private investment evaluation.',
                    'previous_projects' => 'Renewable microgrid pilot for island communities; cold-chain logistics feasibility study; enterprise systems rollout support for a government-linked organization.',
                    'major_clients' => 'Local government units, private agri-logistics operators, and technology implementation partners.',
                    'certifications' => 'SEC registered; standard tax and business permits maintained for proposal due diligence.',
                ]),
                'department' => 'Sample Proponent Corporation',
                'position' => 'External Proponent Representative',
                'default_role_id' => 7,
                'is_active' => true,
                'email_verified_at' => now(),
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
