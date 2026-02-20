<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            ProjectTypeSeeder::class,
            IndustrySeeder::class,
            SectorSeeder::class,
            InvestmentTypeSeeder::class,
            FundingSourceSeeder::class,
            ProjectStageSeeder::class,
            ProjectStatusSeeder::class,
            UserSeeder::class,
            ApprovalWorkflowSeeder::class,
            KpiDefinitionSeeder::class,
            SystemSettingSeeder::class,
            EmailTemplateSeeder::class,
        ]);
    }
}