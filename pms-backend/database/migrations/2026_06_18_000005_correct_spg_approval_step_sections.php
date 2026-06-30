<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sectionMap = [
            'SPG Traditional Equity Funding Approval' => [
                1 => 'intake',
                2 => 'intake',
                3 => 'due_diligence',
                4 => 'requirements',
                5 => 'due_diligence',
                6 => 'management_review',
                7 => 'board_approval',
                8 => 'agreement_fund_release',
            ],
            'SPG NDC-Owned Project Approval' => [
                1 => 'intake',
                2 => 'management_review',
                3 => 'due_diligence',
                4 => 'management_review',
                5 => 'board_approval',
                6 => 'agreement_fund_release',
                7 => 'implementation_monitoring',
            ],
            'SPG Joint Venture Project Approval' => [
                1 => 'intake',
                2 => 'management_review',
                3 => 'due_diligence',
                4 => 'management_review',
                5 => 'board_approval',
                6 => 'board_approval',
                7 => 'board_approval',
                8 => 'board_approval',
                9 => 'board_approval',
                10 => 'agreement_fund_release',
            ],
        ];

        foreach ($sectionMap as $workflowName => $steps) {
            $workflowId = DB::table('approval_workflows')
                ->where('name', $workflowName)
                ->value('id');

            if (!$workflowId) {
                continue;
            }

            foreach ($steps as $stepOrder => $soiSection) {
                DB::table('approval_steps')
                    ->where('workflow_id', $workflowId)
                    ->where('step_order', $stepOrder)
                    ->update(['soi_section' => $soiSection]);
            }
        }
    }

    public function down(): void
    {
        // Data correction only. Keeping corrected SOI sections is safer than
        // restoring heuristic values that placed SPG NEDA/JV steps in Intake.
    }
};
