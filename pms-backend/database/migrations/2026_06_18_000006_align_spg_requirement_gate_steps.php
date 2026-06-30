<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $spgJvProjectIds = DB::table('projects')
            ->where('process_track', 'spg_jv')
            ->pluck('id');

        if ($spgJvProjectIds->isNotEmpty()) {
            $this->retargetRequirements($spgJvProjectIds->all(), [
                'ManCom decision paper, recommendation, or presentation material' => [
                    'item_name' => 'Study evaluation, recommendation, and ManCom presentation material',
                    'source_document' => 'SPG JV Tracking Sheet',
                    'soi_section' => 'management_review',
                    'gate_step' => 'spg_jv_mancom_project_decision',
                ],
                'ManCom decision and endorsement to the Board' => [
                    'source_document' => 'SPG JV Tracking Sheet',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_board_project_approval',
                ],
                'Board paper and approval package' => [
                    'item_name' => 'Board paper and approval package for the JV project',
                    'source_document' => 'SPG JV Tracking Sheet',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_board_project_approval',
                ],
                'Board Resolution or Secretary Certificate' => [
                    'item_name' => 'Board approval record or Secretary Certificate for the JV project',
                    'source_document' => 'SPG JV Tracking Sheet',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_neda_icc',
                ],
                'Investment Agreement, contract, JVA, or signed transaction document' => [
                    'item_name' => 'Signed Joint Venture Agreement',
                    'source_document' => 'SPG JV Tracking Sheet',
                    'soi_section' => 'agreement_fund_release',
                    'gate_step' => null,
                ],
                'Receipt issued by investee company or fund release evidence' => [
                    'source_document' => 'SPG Implementation / Monitoring SOI',
                    'soi_section' => 'implementation_monitoring',
                    'gate_step' => null,
                    'is_required' => false,
                ],
                'NEDA endorsement / ICC requirements as applicable' => [
                    'item_name' => 'NEDA-ICC requirements and endorsement package if required',
                    'source_document' => 'SPG JV Tracking Sheet',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_neda_icc',
                ],
                'NEDA-ICC approval record and Board approval of JVA terms / JV-SC composition' => [
                    'source_document' => 'SPG JV Tracking Sheet',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_selection_award',
                ],
                'JV Selection Committee documents, Notice of Award, and signed JVA' => [
                    'item_name' => 'JV-SC selection proceedings, Board award approval, NOA, and JVA signing evidence',
                    'source_document' => 'SPG JV Tracking Sheet',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_jv_final_award',
                ],
            ]);
        }

        $spgNdcOwnProjectIds = DB::table('projects')
            ->where('process_track', 'spg_ndc_own')
            ->pluck('id');

        if ($spgNdcOwnProjectIds->isNotEmpty()) {
            $this->retargetRequirements($spgNdcOwnProjectIds->all(), [
                'Project concept and initial evaluation report' => [
                    'source_document' => 'SPG NDC-on-Own Tracking Sheet',
                    'soi_section' => 'intake',
                    'gate_step' => null,
                ],
                'ManCom approval to proceed with study or consultancy' => [
                    'source_document' => 'SPG NDC-on-Own Tracking Sheet',
                    'soi_section' => 'management_review',
                    'gate_step' => null,
                ],
                'Consultancy agreement, NTP, and study report' => [
                    'source_document' => 'SPG NDC-on-Own Tracking Sheet',
                    'soi_section' => 'due_diligence',
                    'gate_step' => 'spg_ndc_own_mancom_project_decision',
                ],
                'Study evaluation, recommendation, and ManCom presentation material' => [
                    'source_document' => 'SPG NDC-on-Own Tracking Sheet',
                    'soi_section' => 'management_review',
                    'gate_step' => 'spg_ndc_own_mancom_project_decision',
                ],
                'ManCom decision, directives, and endorsement to the Board' => [
                    'source_document' => 'SPG NDC-on-Own Tracking Sheet',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_ndc_own_board_approval',
                ],
                'Board paper, Board Resolution, or Secretary Certificate' => [
                    'source_document' => 'SPG NDC-on-Own Tracking Sheet',
                    'soi_section' => 'board_approval',
                    'gate_step' => 'spg_ndc_own_ded_construction',
                ],
                'DED TOR/MR, bidding documents, design plans, and specifications' => [
                    'source_document' => 'SPG NDC-on-Own Tracking Sheet',
                    'soi_section' => 'agreement_fund_release',
                    'gate_step' => 'spg_ndc_own_ded_construction',
                ],
                'Construction contract, supervision agreement, completion acceptance, and turn-over record' => [
                    'source_document' => 'SPG NDC-on-Own Tracking Sheet',
                    'soi_section' => 'implementation_monitoring',
                    'gate_step' => null,
                ],
            ]);
        }

        DB::table('project_requirements')
            ->whereIn('project_id', $spgJvProjectIds->merge($spgNdcOwnProjectIds)->all())
            ->where('gate_step', 'monitoring')
            ->update([
                'soi_section' => 'implementation_monitoring',
            ]);
    }

    private function retargetRequirements(array $projectIds, array $map): void
    {
        foreach ($map as $currentName => $updates) {
            DB::table('project_requirements')
                ->whereIn('project_id', $projectIds)
                ->where('owner_type', 'internal')
                ->where('item_name', $currentName)
                ->update($updates);
        }
    }

    public function down(): void
    {
        // Data correction only. Restoring broad gate keys would reintroduce
        // circular blockers for SPG approval steps.
    }
};
