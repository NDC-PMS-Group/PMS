<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Draft', 'color_code' => '#94A3B8', 'is_active' => true],
            ['name' => 'Submitted', 'color_code' => '#6366F1', 'is_active' => true],
            ['name' => 'Pending', 'color_code' => '#FFA500', 'is_active' => true],
            ['name' => 'Returned for Revision', 'color_code' => '#F97316', 'is_active' => true],
            ['name' => 'Initial Completeness Check', 'color_code' => '#F59E0B', 'is_active' => true],
            ['name' => 'For Evaluation', 'color_code' => '#FFD700', 'is_active' => true],
            ['name' => 'Evaluation Ongoing', 'color_code' => '#EAB308', 'is_active' => true],
            ['name' => 'For Approval', 'color_code' => '#1E90FF', 'is_active' => true],
            ['name' => 'For Workgroup Review', 'color_code' => '#38BDF8', 'is_active' => true],
            ['name' => 'For ManCom Review', 'color_code' => '#0EA5E9', 'is_active' => true],
            ['name' => 'For Board Approval', 'color_code' => '#2563EB', 'is_active' => true],
            ['name' => 'Approved', 'color_code' => '#32CD32', 'is_active' => true],
            ['name' => 'Approved with Conditions', 'color_code' => '#9ACD32', 'is_active' => true],
            ['name' => 'Implementation Ongoing', 'color_code' => '#14B8A6', 'is_active' => true],
            ['name' => 'In Progress', 'color_code' => '#00CED1', 'is_active' => true],
            ['name' => 'On Hold', 'color_code' => '#FF8C00', 'is_active' => true],
            ['name' => 'Delayed', 'color_code' => '#DC143C', 'is_active' => true],
            ['name' => 'Completed', 'color_code' => '#228B22', 'is_active' => true],
            ['name' => 'Archived', 'color_code' => '#475569', 'is_active' => true],
            ['name' => 'Divested', 'color_code' => '#64748B', 'is_active' => true],
            ['name' => 'Cancelled', 'color_code' => '#696969', 'is_active' => true],
            ['name' => 'Rejected', 'color_code' => '#8B0000', 'is_active' => true],
        ];

        foreach ($statuses as $status) {
            DB::table('project_statuses')->updateOrInsert(
                ['name' => $status['name']],
                array_merge($status, ['created_at' => now()])
            );
        }
    }
}
