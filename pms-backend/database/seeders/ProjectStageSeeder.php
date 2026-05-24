<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectStageSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            ['name' => 'Intake', 'sequence_order' => 1, 'description' => 'Pre-screening, KYC, LOI, project concept, and proposal receipt.', 'is_active' => true],
            ['name' => 'Requirements', 'sequence_order' => 2, 'description' => 'Citizen Charter response and receipt of complete documentary requirements.', 'is_active' => true],
            ['name' => 'Due Diligence', 'sequence_order' => 3, 'description' => 'Validation, triangulation, feasibility review, financial model, and third-party due diligence.', 'is_active' => true],
            ['name' => 'Management Review', 'sequence_order' => 4, 'description' => 'AGM, Workgroup, Investment Committee where applicable, and ManCom deliberation.', 'is_active' => true],
            ['name' => 'Board Approval', 'sequence_order' => 5, 'description' => 'Board or Board committee approval, conditions, deferral, or rejection.', 'is_active' => true],
            ['name' => 'Agreement & Fund Release', 'sequence_order' => 6, 'description' => 'Agreement preparation, OGCC/legal review, signing, compliance, and fund release.', 'is_active' => true],
            ['name' => 'Implementation & Monitoring', 'sequence_order' => 7, 'description' => 'Monthly summary folder, milestone, covenant, risk, and financial performance monitoring.', 'is_active' => true],
            ['name' => 'Post-Investment Strategy', 'sequence_order' => 8, 'description' => 'Review of redemption, conversion, additional capital, dividend, and exit options.', 'is_active' => true],
            ['name' => 'Divestment', 'sequence_order' => 9, 'description' => 'Legal and financial due diligence, ManCom and Board approval, transfer, and collection.', 'is_active' => true],
            ['name' => 'Completion', 'sequence_order' => 10, 'description' => 'Completed project or closed monitoring cycle.', 'is_active' => true],
        ];

        foreach ($stages as $stage) {
            DB::table('project_stages')->updateOrInsert(
                ['name' => $stage['name']],
                array_merge($stage, ['created_at' => now()])
            );
        }

        $legacyMap = [
            'Proposal' => 'Intake',
            'Evaluation' => 'Due Diligence',
            'Approval' => 'Management Review',
            'Implementation' => 'Implementation & Monitoring',
            'Construction' => 'Implementation & Monitoring',
            'Operation' => 'Implementation & Monitoring',
            'Construction Operation' => 'Implementation & Monitoring',
        ];

        foreach ($legacyMap as $legacyName => $replacementName) {
            $legacy = DB::table('project_stages')->where('name', $legacyName)->first();
            $replacement = DB::table('project_stages')->where('name', $replacementName)->first();

            if (!$legacy || !$replacement) {
                continue;
            }

            DB::table('projects')
                ->where('current_stage_id', $legacy->id)
                ->update(['current_stage_id' => $replacement->id]);

            DB::table('project_stage_history')
                ->where('to_stage_id', $legacy->id)
                ->update(['to_stage_id' => $replacement->id]);

            DB::table('project_stage_history')
                ->where('from_stage_id', $legacy->id)
                ->update(['from_stage_id' => $replacement->id]);

            DB::table('project_stages')
                ->where('id', $legacy->id)
                ->update([
                    'is_active' => false,
                    'description' => "Legacy stage; replaced by {$replacementName}",
                    'sequence_order' => 999,
                ]);
        }
    }
}
