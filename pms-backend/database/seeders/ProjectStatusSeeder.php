<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Pending', 'color_code' => '#FFA500', 'is_active' => true],
            ['name' => 'For Evaluation', 'color_code' => '#FFD700', 'is_active' => true],
            ['name' => 'For Approval', 'color_code' => '#1E90FF', 'is_active' => true],
            ['name' => 'Approved', 'color_code' => '#32CD32', 'is_active' => true],
            ['name' => 'Approved with Conditions', 'color_code' => '#9ACD32', 'is_active' => true],
            ['name' => 'In Progress', 'color_code' => '#00CED1', 'is_active' => true],
            ['name' => 'On Hold', 'color_code' => '#FF8C00', 'is_active' => true],
            ['name' => 'Delayed', 'color_code' => '#DC143C', 'is_active' => true],
            ['name' => 'Completed', 'color_code' => '#228B22', 'is_active' => true],
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
