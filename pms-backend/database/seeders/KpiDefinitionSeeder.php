<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KpiDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $kpis = [
            ['name' => 'Budget Utilization', 'description' => 'Percentage of budget utilized', 'calculation_formula' => '(actual_cost / estimated_cost) * 100', 'unit' => '%', 'target_value' => 100, 'is_active' => true],
            ['name' => 'Timeline Adherence', 'description' => 'Percentage of tasks completed on time', 'calculation_formula' => '(tasks_on_time / total_tasks) * 100', 'unit' => '%', 'target_value' => 90, 'is_active' => true],
            ['name' => 'Project Completion Rate', 'description' => 'Overall project progress', 'calculation_formula' => 'Average of all task progress', 'unit' => '%', 'target_value' => 100, 'is_active' => true],
            ['name' => 'Stakeholder Satisfaction', 'description' => 'Stakeholder satisfaction score', 'calculation_formula' => 'Survey average score', 'unit' => 'score', 'target_value' => 4.5, 'is_active' => true],
            ['name' => 'ROI', 'description' => 'Return on Investment', 'calculation_formula' => '(gains - cost) / cost * 100', 'unit' => '%', 'target_value' => 20, 'is_active' => true],
        ];

        foreach ($kpis as $kpi) {
            DB::table('kpi_definitions')->insert(array_merge($kpi, ['created_at' => now()]));
        }
    }
}