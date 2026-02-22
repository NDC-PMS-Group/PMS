<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectStageSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            ['name' => 'Proposal', 'sequence_order' => 1, 'description' => 'Project proposal stage', 'is_active' => true],
            ['name' => 'Evaluation', 'sequence_order' => 2, 'description' => 'Project evaluation stage', 'is_active' => true],
            ['name' => 'Approval', 'sequence_order' => 3, 'description' => 'Project approval stage', 'is_active' => true],
            ['name' => 'Implementation', 'sequence_order' => 4, 'description' => 'Project implementation stage', 'is_active' => true],
            ['name' => 'Construction', 'sequence_order' => 5, 'description' => 'Project construction stage', 'is_active' => true],
            ['name' => 'Operation', 'sequence_order' => 6, 'description' => 'Project operation stage', 'is_active' => true],
            ['name' => 'Completion', 'sequence_order' => 7, 'description' => 'Project completion stage', 'is_active' => true],
            ['name' => 'Divestment', 'sequence_order' => 8, 'description' => 'Project divestment stage', 'is_active' => true],
        ];

        foreach ($stages as $stage) {
            DB::table('project_stages')->updateOrInsert(
                ['name' => $stage['name']],
                array_merge($stage, ['created_at' => now()])
            );
        }

        // Backward compatibility: split legacy "Construction Operation" into separate stages.
        $legacy = DB::table('project_stages')->where('name', 'Construction Operation')->first();
        $construction = DB::table('project_stages')->where('name', 'Construction')->first();

        if ($legacy && $construction) {
            DB::table('projects')
                ->where('current_stage_id', $legacy->id)
                ->update(['current_stage_id' => $construction->id]);

            DB::table('project_stage_history')
                ->where('to_stage_id', $legacy->id)
                ->update(['to_stage_id' => $construction->id]);

            DB::table('project_stage_history')
                ->where('from_stage_id', $legacy->id)
                ->update(['from_stage_id' => $construction->id]);

            DB::table('project_stages')
                ->where('id', $legacy->id)
                ->update([
                    'is_active' => false,
                    'description' => 'Legacy combined stage; replaced by Construction and Operation',
                    'sequence_order' => 999,
                ]);
        }
    }
}
