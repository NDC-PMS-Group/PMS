<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('default_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('track', 80)->index();
            $table->string('group_name', 150);
            $table->string('item_name', 255);
            $table->string('source_document', 150)->nullable();
            $table->string('owner_type', 50)->default('proponent');
            $table->string('visibility', 50)->default('proponent_visible');
            $table->string('soi_section', 80)->index();
            $table->string('gate_step', 80)->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('svf_only')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('template_file_path', 255)->nullable();
            $table->timestamps();
        });

        $this->seedDefaultRequirements();
    }

    public function down(): void
    {
        Schema::dropIfExists('default_requirements');
    }

    private function seedDefaultRequirements(): void
    {
        $now = now();
        $records = [];

        // 1. SPG JV Track Specific Items
        $spgJvItems = [
            ['1. JV Concept Package', 'Formal project concept or JV proposal submitted for initial SPG review', 'SPG Proposal Requirements', false, 'proponent', 'proponent_visible', 'intake', null, true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE FROM SPG] Project Summary Sheet.docx'],
            ['1. JV Concept Package', 'Company profile, capability statement, or pitch deck', 'SPG Proposal Requirements', false, 'proponent', 'proponent_visible', 'intake', null, true, null],
            ['1. JV Concept Package', 'Authority to submit or authorized representative certification', 'SPG Proposal Requirements', false, 'proponent', 'proponent_visible', 'intake', null, true, null],
            ['2. ManCom Approval to Proceed', 'JV project concept and initial evaluation note', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'intake', null, true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['2. ManCom Approval to Proceed', 'ManCom approval to proceed with study and budget allocation', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'management_review', null, true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['3. Consultancy Procurement and Study', 'Budget estimates, TOR, Materials Requisition, and bidding documents', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'due_diligence', null, true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['3. Consultancy Procurement and Study', 'Consultancy agreement, Notice to Proceed, and study report', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'due_diligence', 'spg_jv_mancom_project_decision', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['4. ManCom JV Project Decision', 'Study evaluation, recommendation, and ManCom presentation material', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'management_review', 'spg_jv_mancom_project_decision', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['4. ManCom JV Project Decision', 'ManCom decision and endorsement to the Board', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_board_project_approval', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['5. Board Approval of JV Project', 'Board paper and approval package for the JV project', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_board_project_approval', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['5. Board Approval of JV Project', 'Board approval record or Secretary Certificate for the JV project', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_neda_icc', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['6. NEDA-ICC and JV-SC', 'NEDA-ICC requirements and endorsement package if required', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_neda_icc', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['6. NEDA-ICC and JV-SC', 'NEDA-ICC approval record or applicability waiver', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_jva_terms_jvsc', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['6. NEDA-ICC and JV-SC', 'Board approval of NEDA-approved JVA terms and JV-SC composition', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_selection_award', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['7. JV Partner Selection and Award', 'JV selection documents, IAESP, publication, and eligibility records', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_selection_award', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['7. JV Partner Selection and Award', 'JV-SC selection proceedings and recommendation to award', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_final_award', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['7. JV Partner Selection and Award', 'Board approval of award and Notice of Award', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_jva_signing', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['8. NOA and JVA Signing', 'Signed Joint Venture Agreement', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'agreement_fund_release', null, true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
        ];

        foreach ($spgJvItems as $index => $item) {
            $records[] = [
                'track' => 'spg_jv',
                'group_name' => $item[0],
                'item_name' => $item[1],
                'source_document' => $item[2],
                'svf_only' => $item[3],
                'owner_type' => $item[4],
                'visibility' => $item[5],
                'soi_section' => $item[6],
                'gate_step' => $item[7],
                'is_required' => $item[8],
                'sort_order' => ($index + 1) * 10,
                'template_file_path' => $item[9],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 2. SPG NDC Owned Track Specific Items
        $spgOwnItems = [
            ['1. Concept and ManCom Instruction', 'Project concept and initial evaluation report', 'SPG SOI-01 Section 8', false, 'internal', 'internal_only', 'intake', null, true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['1. Concept and ManCom Instruction', 'ManCom approval to proceed with study or consultancy', 'SPG SOI-01 Section 8', false, 'internal', 'internal_only', 'management_review', null, true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['2. Consultancy Study Procurement', 'Budget estimates, TOR, Materials Requisition, and bidding documents', 'SPG tracking sheet - NDC on its own', false, 'internal', 'internal_only', 'due_diligence', null, true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['2. Consultancy Study Procurement', 'Consultancy agreement, NTP, and study report', 'SPG SOI-01 Section 8', false, 'internal', 'internal_only', 'due_diligence', 'spg_ndc_own_mancom_project_decision', true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['3. Management and Board Decision', 'Study evaluation, recommendation, and ManCom presentation material', 'SPG tracking sheet - NDC on its own', false, 'internal', 'internal_only', 'management_review', 'spg_ndc_own_mancom_project_decision', true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['3. Management and Board Decision', 'ManCom decision, directives, and endorsement to the Board', 'SPG tracking sheet - NDC on its own', false, 'internal', 'internal_only', 'board_approval', 'spg_ndc_own_board_approval', true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['3. Management and Board Decision', 'Board paper, Board Resolution, or Secretary Certificate', 'SPG SOI-01 Section 8', false, 'internal', 'internal_only', 'board_approval', 'spg_ndc_own_ded_construction', true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['4. DED and Construction', 'DED TOR/MR, bidding documents, design plans, and specifications', 'SPG tracking sheet - NDC on its own', false, 'internal', 'internal_only', 'agreement_fund_release', 'spg_ndc_own_ded_construction', true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['4. DED and Construction', 'Construction contract, supervision agreement, completion acceptance, and turn-over record', 'SPG SOI-01 Section 8', false, 'internal', 'internal_only', 'implementation_monitoring', null, true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
        ];

        foreach ($spgOwnItems as $index => $item) {
            $records[] = [
                'track' => 'spg_ndc_own',
                'group_name' => $item[0],
                'item_name' => $item[1],
                'source_document' => $item[2],
                'svf_only' => $item[3],
                'owner_type' => $item[4],
                'visibility' => $item[5],
                'soi_section' => $item[6],
                'gate_step' => $item[7],
                'is_required' => $item[8],
                'sort_order' => ($index + 1) * 10,
                'template_file_path' => $item[9],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 3. Implementation and Monitoring
        $monitoringItems = [
            ['1. Summary Folder', 'Signed agreements, Board approval, release-of-funds records, and project brief', 'BDG/SPG implementation SOI', false, 'internal', 'internal_only', 'implementation_monitoring', null, true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE FROM SPG] Project Summary Sheet.docx'],
            ['1. Summary Folder', 'Schedule of drawdowns, covenants, dividends/coupons, amortization, or repayment terms', 'BDG/SPG implementation SOI', false, 'internal', 'internal_only', 'implementation_monitoring', null, true, null],
            ['2. Monitoring Evidence', 'Correspondence, statements of account, financial statements, Board papers, and risk/compliance registers', 'BDG/SPG implementation SOI', false, 'internal', 'internal_only', 'implementation_monitoring', null, true, null],
            ['2. Monitoring Evidence', 'Milestone, issues, next steps, quarterly management/COA update, and post-investment notes', 'BDG/SPG implementation SOI', false, 'internal', 'internal_only', 'implementation_monitoring', null, true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['3. Adjustment / Modification Approval', 'ManCom or Board endorsement records for restructuring, equity changes, or divestment adjustments', 'BDG/SPG implementation SOI', false, 'internal', 'internal_only', 'implementation_monitoring', null, true, null],
        ];

        foreach ($monitoringItems as $index => $item) {
            $records[] = [
                'track' => 'implementation_monitoring',
                'group_name' => $item[0],
                'item_name' => $item[1],
                'source_document' => $item[2],
                'svf_only' => $item[3],
                'owner_type' => $item[4],
                'visibility' => $item[5],
                'soi_section' => $item[6],
                'gate_step' => null,
                'is_required' => true,
                'sort_order' => ($index + 1) * 10,
                'template_file_path' => $item[9] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 4. Divestment
        $divestmentItems = [
            ['1. Divestment Start', 'ManCom-approved divestment recommendation, external offer, or authority to proceed', 'SPG SOI-03 Section 6.1', false, 'internal', 'internal_only', 'divestment', null, true, null],
            ['2. Due Diligence', 'Legal due diligence report or legal memo', 'SPG SOI-03 Section 6.2', false, 'internal', 'internal_only', 'due_diligence', null, true, null],
            ['2. Due Diligence', 'Financial due diligence, asset appraisal, pricing basis, and updated financial statements', 'SPG SOI-03 Section 6.2', false, 'internal', 'internal_only', 'due_diligence', null, true, null],
            ['3. Approvals', 'ManCom paper and approval of proposed transfer terms', 'SPG SOI-03 Section 6.3', false, 'internal', 'internal_only', 'management_review', null, true, null],
            ['3. Approvals', 'Board paper, Board approval, or Secretary Certificate', 'SPG SOI-03 Section 6.4', false, 'internal', 'internal_only', 'board_approval', null, true, null],
            ['4. Transfer and Collection', 'Transfer documents, payment evidence, receipts, and closing records', 'SPG SOI-03 Section 6.5', false, 'internal', 'internal_only', 'agreement_fund_release', null, true, null],
        ];

        foreach ($divestmentItems as $index => $item) {
            $records[] = [
                'track' => 'divestment',
                'group_name' => $item[0],
                'item_name' => $item[1],
                'source_document' => $item[2],
                'svf_only' => $item[3],
                'owner_type' => $item[4],
                'visibility' => $item[5],
                'soi_section' => $item[6],
                'gate_step' => null,
                'is_required' => true,
                'sort_order' => ($index + 1) * 10,
                'template_file_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 5. Fallback Checklist items (assigned to BDG Investment, SPG Traditional, and defaults)
        $fallbackTracks = ['bdg_investment', 'spg_traditional'];
        $fallbackItems = [
            ['1. Intake Pack', 'Brochure, pitch deck, or company profile', 'BDG/SPG SOI', false, 'proponent', 'proponent_visible', 'intake', null, true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Checklist of Requirements.xlsx'],
            ['1. Intake Pack', 'Website or product/company page', 'BDG eligibility checklist', false, 'proponent', 'proponent_visible', 'intake', null, false, null],
            ['1. Intake Pack', 'Letter of Intent or project concept', 'Proposal requirements', false, 'proponent', 'proponent_visible', 'intake', null, true, null],
            ['1. Intake Pack', 'Non-Disclosure Agreement and Data Privacy Consent', 'NDC templates', false, 'proponent', 'proponent_visible', 'intake', null, false, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Non-Disclosure Agreement (NDA).docx'],
            ['1. Intake Pack', 'Secretary Certificate or authority to submit', 'Checklist of requirements', false, 'proponent', 'proponent_visible', 'intake', null, false, null],

            ['2. NDC Screening / Response', 'Response letter and documentary checklist issued to proponent', 'BDG checklist / SPG official checklist', false, 'internal', 'internal_only', 'requirements', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['2. Proposal Summary', 'Project description, technology, location, and market reason', '1st level proposal format', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE FROM SPG] Project Summary Sheet.docx'],
            ['2. Proposal Summary', 'Target beneficiaries, social/economic benefits, and jobs generated', '1st level proposal format', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE FROM SPG] Jobs Generated.docx'],
            ['2. Proposal Summary', 'Estimated project cost, projected revenue, NDC participation, and schedule', '1st level proposal format', false, 'proponent', 'proponent_visible', 'requirements', null, true, null],
            ['2. Proposal Summary', 'Proponent background, shareholders, affiliates, and track record', '1st level proposal format', false, 'proponent', 'proponent_visible', 'requirements', null, true, null],

            ['3. Company / Legal / Financial Documents', 'SEC or DTI registration, Articles, and By-Laws', 'Official checklist', false, 'proponent', 'proponent_visible', 'requirements', null, true, null],
            ['3. Company / Legal / Financial Documents', 'Audited financial statements for the last three years, BIR, and tax clearance', 'Official checklist', false, 'proponent', 'proponent_visible', 'requirements', null, true, null],
            ['3. Company / Legal / Financial Documents', 'Proof of site ownership, authority, or project location control', 'BDG SOI', false, 'proponent', 'proponent_visible', 'requirements', null, true, null],

            ['4. Evaluation Documents', 'Feasibility Study, Pre-FS, or Business Plan', '2nd level proposal format', false, 'proponent', 'proponent_visible', 'due_diligence', null, true, null],
            ['4. Evaluation Documents', 'Financial model, profitability analysis, and use of proceeds', '2nd level proposal format', false, 'proponent', 'proponent_visible', 'due_diligence', null, true, null],
            ['4. Evaluation Documents', 'Risk register, ESG/GAD write-up if applicable, and mitigation plan', 'Proposal requirements', false, 'proponent', 'proponent_visible', 'due_diligence', null, true, null],
            ['4. Evaluation Documents', 'Due diligence or credit/background investigation report', 'BDG SOI', false, 'internal', 'internal_only', 'due_diligence', null, true, null],
            ['4. Evaluation Documents', 'Investment criteria assessment with at least three qualifying criteria', 'Official checklist', false, 'internal', 'internal_only', 'due_diligence', null, true, 'SPG/2019 Revised Investment Guidelines.pdf'],

            ['5. Internal Evaluation / Endorsement', 'Investment Committee evaluation material', 'BDG SOI - SVF only', true, 'internal', 'internal_only', 'management_review', null, true, null],
            ['5. Internal Evaluation / Endorsement', 'ManCom decision paper, recommendation, or presentation material', 'BDG/SPG SOI', false, 'internal', 'internal_only', 'management_review', 'mancom', true, null],
            ['5. Internal Evaluation / Endorsement', 'ManCom decision and endorsement to the Board', 'SPG official checklist / tracking sheet', false, 'internal', 'internal_only', 'board_approval', 'board', true, null],
            ['6. Board Evaluation', 'Board paper and approval package', 'BDG/SPG SOI', false, 'internal', 'internal_only', 'board_approval', 'board', true, null],
            ['6. Board Evaluation', 'Board Resolution or Secretary Certificate', 'BDG checklist', false, 'internal', 'internal_only', 'board_approval', 'board', true, null],
            ['7. Agreement and Fund Release', 'Investment Agreement, contract, JVA, or signed transaction document', 'Official checklist', false, 'internal', 'internal_only', 'agreement_fund_release', 'fund_release', true, null],
            ['7. Agreement and Fund Release', 'Receipt issued by investee company or fund release evidence', 'BDG checklist', false, 'internal', 'internal_only', 'agreement_fund_release', null, true, null],

            ['8. Monitoring', 'Project Summary Sheet with milestones, covenants, risks, issues, and next steps', 'Implementation SOI', false, 'internal', 'internal_only', 'implementation_monitoring', 'monitoring', true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE FROM SPG] Project Summary Sheet.docx'],
            ['8. Monitoring', 'Jobs generated, financial updates, and quarterly reporting evidence', 'NDC templates / COA tracking sheet', false, 'internal', 'internal_only', 'implementation_monitoring', 'monitoring', true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE FROM SPG] Jobs Generated.docx'],
        ];

        foreach ($fallbackTracks as $track) {
            foreach ($fallbackItems as $index => $item) {
                $records[] = [
                    'track' => $track,
                    'group_name' => $item[0],
                    'item_name' => $item[1],
                    'source_document' => $item[2],
                    'svf_only' => $item[3],
                    'owner_type' => $item[4],
                    'visibility' => $item[5],
                    'soi_section' => $item[6],
                    'gate_step' => $item[7],
                    'is_required' => (bool) $item[8],
                    'sort_order' => ($index + 1) * 10,
                    'template_file_path' => $item[9] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('default_requirements')->insert($records);
    }
};
