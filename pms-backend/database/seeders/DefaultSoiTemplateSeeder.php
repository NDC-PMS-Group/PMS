<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DefaultSoiTemplateSeeder extends Seeder
{
    private const TRACKS = [
        'bdg_investment',
        'spg_traditional',
        'spg_jv',
        'spg_ndc_own',
        'implementation_monitoring',
        'divestment',
    ];

    public function run(): void
    {
        if (Schema::hasTable('default_requirements')) {
            DB::table('default_requirements')->whereIn('track', self::TRACKS)->delete();
            DB::table('default_requirements')->insert($this->requirementRecords());
        }

        if (Schema::hasTable('default_tasks')) {
            DB::table('default_tasks')->whereIn('track', self::TRACKS)->delete();
            DB::table('default_tasks')->insert($this->taskRecords());
        }
    }

    private function requirementRecords(): array
    {
        $now = now();
        $records = [];

        $investmentChecklist = [
            ['1. Eligibility Screening', 'Brochure', 'BDG Checklist of Requirements', false, 'proponent', 'proponent_visible', 'intake', null, true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Checklist of Requirements.xlsx'],
            ['1. Eligibility Screening', 'Pitch Deck', 'BDG Checklist of Requirements', false, 'proponent', 'proponent_visible', 'intake', null, true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Checklist of Requirements.xlsx'],
            ['1. Eligibility Screening', 'Website or product/company page', 'BDG Checklist of Requirements', false, 'proponent', 'proponent_visible', 'intake', null, false, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Checklist of Requirements.xlsx'],
            ['1. Eligibility Screening', 'Based and registered in the Philippines', 'SPG Official Checklist of Requirements', false, 'internal', 'internal_only', 'intake', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['1. Eligibility Screening', 'No pending accountabilities with DTI, DOST, DICT, GOCCs, or other government agencies', 'SPG Official Checklist of Requirements', false, 'internal', 'internal_only', 'intake', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['1. Eligibility Screening', 'Declared all government funding previously received', 'SPG Official Checklist of Requirements', false, 'internal', 'internal_only', 'intake', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['1. Eligibility Screening', 'At least one year in operations', 'SPG Official Checklist of Requirements', false, 'internal', 'internal_only', 'intake', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['1. Eligibility Screening', 'At least three NDC investment criteria satisfied', 'SPG Official Checklist of Requirements', false, 'internal', 'internal_only', 'intake', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['1. Eligibility Screening', 'SVF minimum investment size of PHP 5M', 'SPG Official Checklist of Requirements - SVF only', true, 'internal', 'internal_only', 'intake', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['1. Eligibility Screening', 'Traditional project minimum investment size of PHP 15M', 'SPG Official Checklist of Requirements', false, 'internal', 'internal_only', 'intake', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],

            ['2. Preliminary Requirements', 'Formal letter of project proposal addressed to the NDC General Manager', 'NDC Proposal Requirements - 1st level', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_1st level.docx'],
            ['2. Preliminary Requirements', 'Letter of Intent or endorsement letter from other government agency', 'SPG Official Checklist of Requirements', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['2. Preliminary Requirements', 'Non-Disclosure Agreement', 'BDG/SPG NDA template', false, 'proponent', 'proponent_visible', 'requirements', null, false, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Non-Disclosure Agreement (NDA).docx'],
            ['2. Preliminary Requirements', 'Secretary Certificate, Board Resolution, or authority to submit', 'BDG Checklist of Requirements', false, 'proponent', 'proponent_visible', 'requirements', null, false, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Checklist of Requirements.xlsx'],
            ['2. Preliminary Requirements', 'Data Privacy Consent Form', 'BDG Checklist of Requirements', false, 'proponent', 'proponent_visible', 'requirements', null, false, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Checklist of Requirements.xlsx'],
            ['2. Preliminary Requirements', 'NDC response letter and documentary checklist issued to proponent', 'BDG Checklist of Requirements', false, 'internal', 'internal_only', 'requirements', null, true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Checklist of Requirements.xlsx'],

            ['3. Project Proposal Details', 'Project description and technical description of project or technology', 'NDC Proposal Requirements - 1st level', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_1st level.docx'],
            ['3. Project Proposal Details', 'Project location and reason for choice of location', 'NDC Proposal Requirements - 1st level', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_1st level.docx'],
            ['3. Project Proposal Details', 'Target market, potential competition, and industry or market analysis', 'SPG Official Checklist of Requirements', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['3. Project Proposal Details', 'Target beneficiaries and expected social/economic benefits', 'NDC Proposal Requirements - 1st level', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_1st level.docx'],
            ['3. Project Proposal Details', 'Estimated total project cost, projected revenue, NDC participation, and target implementation schedule', 'NDC Proposal Requirements - 1st level', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_1st level.docx'],
            ['3. Project Proposal Details', 'Project proponent background, owners/shareholders, previous projects, existing projects, affiliates, and subsidiaries', 'NDC Proposal Requirements - 1st level', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_1st level.docx'],

            ['4. Documentary Requirements', 'Pitch Deck or Data Room', 'BDG Checklist of Requirements', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Checklist of Requirements.xlsx'],
            ['4. Documentary Requirements', 'Term Sheet', 'BDG Checklist of Requirements', false, 'proponent', 'proponent_visible', 'requirements', null, false, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Checklist of Requirements.xlsx'],
            ['4. Documentary Requirements', 'SEC or DTI registration, Articles of Incorporation, and By-Laws', 'SPG Official Checklist of Requirements', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['4. Documentary Requirements', 'Audited financial statements for the last three years, BIR registration, and tax clearance', 'BDG/SPG Checklist of Requirements', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['4. Documentary Requirements', 'Corporate structure', 'SPG Official Checklist of Requirements', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['4. Documentary Requirements', 'Management team including CVs and NBI clearance', 'SPG Official Checklist of Requirements', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['4. Documentary Requirements', 'Licenses, permits, accreditation, and trade registrations', 'NDC Proposal Requirements - 2nd level', false, 'proponent', 'proponent_visible', 'requirements', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_2nd level.pdf'],
            ['4. Documentary Requirements', 'Support team information, if available', 'SPG Official Checklist of Requirements', false, 'proponent', 'proponent_visible', 'requirements', null, false, 'SPG/SPG Official Checklist of Requirements.pdf'],

            ['5. Feasibility Study / Business Plan', 'Detailed Feasibility Study, Pre-FS, or Business Plan', 'NDC Proposal Requirements - 2nd level', false, 'proponent', 'proponent_visible', 'due_diligence', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_2nd level.pdf'],
            ['5. Feasibility Study / Business Plan', 'Executive summary', 'NDC Proposal Requirements - 2nd level', false, 'proponent', 'proponent_visible', 'due_diligence', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_2nd level.pdf'],
            ['5. Feasibility Study / Business Plan', 'Industry and market analysis', 'NDC Proposal Requirements - 2nd level', false, 'proponent', 'proponent_visible', 'due_diligence', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_2nd level.pdf'],
            ['5. Feasibility Study / Business Plan', 'Regulatory framework and sector analysis', 'NDC Proposal Requirements - 2nd level', false, 'proponent', 'proponent_visible', 'due_diligence', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_2nd level.pdf'],
            ['5. Feasibility Study / Business Plan', 'Ten-year financial projections and financial statements', 'NDC Proposal Requirements - 2nd level', false, 'proponent', 'proponent_visible', 'due_diligence', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_2nd level.pdf'],
            ['5. Feasibility Study / Business Plan', 'Financial return analysis including FIRR, FNPV, EIRR, ENPV, ROI, BCR, DCF, WACC, payback period, and sensitivity analysis', 'NDC Proposal Requirements - 2nd level', false, 'proponent', 'proponent_visible', 'due_diligence', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_2nd level.pdf'],
            ['5. Feasibility Study / Business Plan', 'Project cost breakdown, proposed financial structure, sources of funding, and use of proceeds', 'NDC Proposal Requirements - 2nd level', false, 'proponent', 'proponent_visible', 'due_diligence', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_2nd level.pdf'],
            ['5. Feasibility Study / Business Plan', 'Risk analysis and mitigating factors or strategies', 'NDC Proposal Requirements - 2nd level', false, 'proponent', 'proponent_visible', 'due_diligence', null, true, 'SPG/Forms and Templates/Proposal Suggested Format/NDC Proposal Requirements_2nd level.pdf'],
            ['5. Feasibility Study / Business Plan', 'Company or technology valuation', 'SPG Official Checklist of Requirements', false, 'proponent', 'proponent_visible', 'due_diligence', null, false, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['5. Feasibility Study / Business Plan', 'Credit and background investigation', 'SPG Official Checklist of Requirements', false, 'internal', 'internal_only', 'due_diligence', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['5. Feasibility Study / Business Plan', 'Gender and Development (GAD) checklist, if applicable', 'SPG Official Checklist of Requirements', false, 'proponent', 'proponent_visible', 'due_diligence', null, false, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['5. Feasibility Study / Business Plan', 'Environmental, Social, and Governance (ESG) write-up, if applicable', 'SPG Official Checklist of Requirements', false, 'proponent', 'proponent_visible', 'due_diligence', null, false, 'SPG/SPG Official Checklist of Requirements.pdf'],

            ['6. Initial Evaluation', 'Initial evaluation result: approved, disapproved, or others', 'SPG Official Checklist of Requirements', false, 'internal', 'internal_only', 'due_diligence', null, true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['6. Initial Evaluation', 'Investment priority and criteria assessment', 'SPG Official Checklist of Requirements', false, 'internal', 'internal_only', 'due_diligence', null, true, 'SPG/2019 Revised Investment Guidelines.pdf'],
            ['6. Initial Evaluation', 'Investment Committee evaluation material', 'BDG Checklist of Requirements - SVF only', true, 'internal', 'internal_only', 'management_review', null, true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Checklist of Requirements.xlsx'],

            ['7. Management Committee Evaluation', 'ManCom decision paper, recommendation, or presentation material', 'BDG/SPG SOI', false, 'internal', 'internal_only', 'management_review', 'mancom', true, null],
            ['7. Management Committee Evaluation', 'ManCom decision and endorsement to the Board', 'SPG Official Checklist of Requirements', false, 'internal', 'internal_only', 'board_approval', 'board', true, 'SPG/SPG Official Checklist of Requirements.pdf'],
            ['8. Board Evaluation', 'Board paper and approval package', 'BDG/SPG SOI', false, 'internal', 'internal_only', 'board_approval', 'board', true, null],
            ['8. Board Evaluation', 'Board Resolution or Secretary Certificate', 'BDG Checklist of Requirements', false, 'internal', 'internal_only', 'board_approval', 'board', true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Checklist of Requirements.xlsx'],
            ['9. Fund Deployment', 'Investment Agreement or Contract', 'BDG Checklist of Requirements', false, 'internal', 'internal_only', 'agreement_fund_release', 'fund_release', true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Checklist of Requirements.xlsx'],
            ['9. Fund Deployment', 'Receipt issued by investee company', 'BDG Checklist of Requirements', false, 'internal', 'internal_only', 'agreement_fund_release', 'fund_release', true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE] Checklist of Requirements.xlsx'],
            ['10. Monitoring', 'Project Summary Sheet with milestones, chronology, status, issues, and next steps', 'Project Summary Sheet template', false, 'internal', 'internal_only', 'implementation_monitoring', 'monitoring', true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE FROM SPG] Project Summary Sheet.docx'],
            ['10. Monitoring', 'Jobs generated report', 'Jobs Generated template', false, 'internal', 'internal_only', 'implementation_monitoring', 'monitoring', true, 'Business Development Group (BDG)/BDG Project Templates/[TEMPLATE FROM SPG] Jobs Generated.docx'],
        ];

        foreach (['bdg_investment', 'spg_traditional'] as $track) {
            foreach ($investmentChecklist as $index => $item) {
                $records[] = $this->requirementRecord($track, $item, $index, $now);
            }
        }

        foreach ($this->spgJvRequirements() as $index => $item) {
            $records[] = $this->requirementRecord('spg_jv', $item, $index, $now);
        }

        foreach ($this->spgNdcOwnedRequirements() as $index => $item) {
            $records[] = $this->requirementRecord('spg_ndc_own', $item, $index, $now);
        }

        foreach ($this->monitoringRequirements() as $index => $item) {
            $records[] = $this->requirementRecord('implementation_monitoring', $item, $index, $now);
        }

        foreach ($this->divestmentRequirements() as $index => $item) {
            $records[] = $this->requirementRecord('divestment', $item, $index, $now);
        }

        return $records;
    }

    private function requirementRecord(string $track, array $item, int $index, $now): array
    {
        return [
            'track' => $track,
            'group_name' => $item[0],
            'item_name' => $item[1],
            'source_document' => $item[2],
            'svf_only' => (bool) $item[3],
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

    private function spgJvRequirements(): array
    {
        return [
            ['1. Project Concept', 'Formal project concept or JV proposal submitted for initial SPG review', 'SPG JV Tracking Sheet', false, 'proponent', 'proponent_visible', 'intake', null, true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['1. Project Concept', 'Preparation of project concept', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'intake', null, true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['1. Project Concept', 'Presentation of project concept to ManCom for decision', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'management_review', null, true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['2. Consultancy Services and Study', 'Budget estimates and TOR for procurement of consultancy services', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'due_diligence', null, true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['2. Consultancy Services and Study', 'Materials Requisition approval', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'due_diligence', null, true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['2. Consultancy Services and Study', 'Public bidding records for consultancy services', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'due_diligence', null, true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['2. Consultancy Services and Study', 'Notice to Proceed and final study report', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'due_diligence', 'spg_jv_mancom_project_decision', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['3. ManCom and Board Approval', 'Evaluation of study and recommendation', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'management_review', 'spg_jv_mancom_project_decision', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['3. ManCom and Board Approval', 'ManCom approval and endorsement to the Board', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_board_project_approval', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['3. ManCom and Board Approval', 'Board approval of proposed project', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_neda_icc', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['4. NEDA-ICC Process', 'NEDA-ICC requirements such as EIS/IEE/ECC/CNC, DOF-CAG review, RDC endorsement, or OGCC opinion if applicable', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_neda_icc', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['4. NEDA-ICC Process', 'JV proposal submission to NEDA-ICC', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_neda_icc', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['4. NEDA-ICC Process', 'NEDA-ICC approval or applicability waiver', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_jva_terms_jvsc', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['5. JVA Terms and JV-SC', 'Board approval of NEDA-approved JVA terms and JV-SC composition', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_selection_award', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['6. JV Partner Selection and Award', 'JV selection documents', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_selection_award', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['6. JV Partner Selection and Award', 'JV-SC selection proceedings and recommendation to award', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_final_award', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['6. JV Partner Selection and Award', 'Board approval of JVA award to winning private sector participant', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_jv_jva_signing', true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['7. NOA and JVA Signing', 'Notice of Award issued to winning private sector participant', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'agreement_fund_release', null, true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
            ['7. NOA and JVA Signing', 'Signed Joint Venture Agreement', 'SPG JV Tracking Sheet', false, 'internal', 'internal_only', 'agreement_fund_release', null, true, 'SPG/SOI/SOI - Tracking Sheet - JV Project - final.docx'],
        ];
    }

    private function spgNdcOwnedRequirements(): array
    {
        return [
            ['1. Project Concept', 'Project concept prepared from ManCom instruction', 'SPG NDC-Owned Tracking Sheet', false, 'internal', 'internal_only', 'intake', null, true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['1. Project Concept', 'Presentation of project concept to ManCom for decision', 'SPG NDC-Owned Tracking Sheet', false, 'internal', 'internal_only', 'management_review', null, true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['2. Consultancy Services and Study', 'Budget estimates and TOR for procurement of consultancy services', 'SPG NDC-Owned Tracking Sheet', false, 'internal', 'internal_only', 'due_diligence', null, true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['2. Consultancy Services and Study', 'Materials Requisition approval', 'SPG NDC-Owned Tracking Sheet', false, 'internal', 'internal_only', 'due_diligence', null, true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['2. Consultancy Services and Study', 'Public bidding records for consultancy services', 'SPG NDC-Owned Tracking Sheet', false, 'internal', 'internal_only', 'due_diligence', null, true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['2. Consultancy Services and Study', 'Notice to Proceed and final study report', 'SPG NDC-Owned Tracking Sheet', false, 'internal', 'internal_only', 'due_diligence', 'spg_ndc_own_mancom_project_decision', true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['3. ManCom and Board Decision', 'Evaluation of study and presentation/recommendation', 'SPG NDC-Owned Tracking Sheet', false, 'internal', 'internal_only', 'management_review', 'spg_ndc_own_mancom_project_decision', true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['3. ManCom and Board Decision', 'ManCom approval and endorsement to the Board', 'SPG NDC-Owned Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_ndc_own_board_approval', true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['3. ManCom and Board Decision', 'Board approval, Board Resolution, or Secretary Certificate', 'SPG NDC-Owned Tracking Sheet', false, 'internal', 'internal_only', 'board_approval', 'spg_ndc_own_ded_construction', true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['4. DED and Construction Procurement', 'Budget estimates and TOR for DED public bidding', 'SPG NDC-Owned Tracking Sheet', false, 'internal', 'internal_only', 'agreement_fund_release', 'spg_ndc_own_ded_construction', true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['4. DED and Construction Procurement', 'Materials Requisition for DED', 'SPG NDC-Owned Tracking Sheet', false, 'internal', 'internal_only', 'agreement_fund_release', 'spg_ndc_own_ded_construction', true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['4. DED and Construction Procurement', 'DED consultancy bidding, NTP, and approved DED', 'SPG NDC-Owned Tracking Sheet', false, 'internal', 'internal_only', 'agreement_fund_release', 'spg_ndc_own_ded_construction', true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['4. DED and Construction Procurement', 'Construction bidding and signed construction agreement', 'SPG NDC-Owned Tracking Sheet', false, 'internal', 'internal_only', 'agreement_fund_release', 'spg_ndc_own_turnover', true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
            ['5. Construction Implementation and Turn-over', 'Construction completion, acceptance, and turn-over records', 'SPG NDC-Owned Tracking Sheet', false, 'internal', 'internal_only', 'implementation_monitoring', null, true, 'SPG/SOI/SOI - Tracking Sheet - NDC on its Own - final.docx'],
        ];
    }

    private function monitoringRequirements(): array
    {
        return [
            ['1. Project Summary Folder', 'Project Summary Sheet', 'Project Summary Sheet template', false, 'internal', 'internal_only', 'implementation_monitoring', null, true, 'SPG/Forms and Templates/[Template] Project Summary Sheet.docx'],
            ['1. Project Summary Folder', 'Schedule of implementation and milestones', 'Project Summary Sheet template', false, 'internal', 'internal_only', 'implementation_monitoring', null, true, 'SPG/Forms and Templates/[Template] Project Summary Sheet.docx'],
            ['1. Project Summary Folder', 'Chronology of important events', 'Project Summary Sheet template', false, 'internal', 'internal_only', 'implementation_monitoring', null, true, 'SPG/Forms and Templates/[Template] Project Summary Sheet.docx'],
            ['2. Monitoring Evidence', 'Status, issues or problems, and next steps', 'Project Summary Sheet template', false, 'internal', 'internal_only', 'implementation_monitoring', null, true, 'SPG/Forms and Templates/[Template] Project Summary Sheet.docx'],
            ['2. Monitoring Evidence', 'Financial updates, drawdowns, covenants, dividends/coupons, amortization, or repayment terms', 'BDG/SPG Implementation Monitoring', false, 'internal', 'internal_only', 'implementation_monitoring', null, true, null],
            ['2. Monitoring Evidence', 'Report on Jobs Generated', 'Jobs Generated template', false, 'internal', 'internal_only', 'implementation_monitoring', null, true, 'SPG/Forms and Templates/[Template] New Jobs Generated.docx'],
            ['3. Adjustment / Modification Approval', 'ManCom or Board endorsement records for restructuring, equity changes, or divestment adjustments', 'BDG-DI-SOI-02', false, 'internal', 'internal_only', 'implementation_monitoring', null, true, 'Business Development Group (BDG)/BDG SOI /BDG-DI-SOI-02 - Project Implementation, Monitoring, Post-Investment Strategy (as of May 9, 2025).pdf'],
            ['4. Post-Investment Strategy', 'Post-investment strategy review, redemption, conversion, dividend, restructuring, or exit option recommendation', 'BDG-DI-SOI-02', false, 'internal', 'internal_only', 'post_investment_strategy', null, true, 'Business Development Group (BDG)/BDG SOI /BDG-DI-SOI-02 - Project Implementation, Monitoring, Post-Investment Strategy (as of May 9, 2025).pdf'],
        ];
    }

    private function divestmentRequirements(): array
    {
        return [
            ['1. Divestment Start', 'ManCom-approved divestment recommendation, external offer, or authority to proceed', 'SPG SOI-03 Project Divestment', false, 'internal', 'internal_only', 'divestment', null, true, 'SPG/SOI/SOI - 3 Project Divestment_signed.pdf'],
            ['2. Due Diligence', 'Legal due diligence report or legal memo', 'SPG SOI-03 Project Divestment', false, 'internal', 'internal_only', 'divestment', null, true, 'SPG/SOI/SOI - 3 Project Divestment_signed.pdf'],
            ['2. Due Diligence', 'Financial due diligence, asset appraisal, pricing basis, and updated financial statements', 'SPG SOI-03 Project Divestment', false, 'internal', 'internal_only', 'divestment', null, true, 'SPG/SOI/SOI - 3 Project Divestment_signed.pdf'],
            ['3. ManCom Approval', 'ManCom paper and approval of proposed transfer terms', 'SPG SOI-03 Project Divestment', false, 'internal', 'internal_only', 'divestment', null, true, 'SPG/SOI/SOI - 3 Project Divestment_signed.pdf'],
            ['4. Board Approval', 'Board paper, Board approval, or Secretary Certificate', 'SPG SOI-03 Project Divestment', false, 'internal', 'internal_only', 'divestment', null, true, 'SPG/SOI/SOI - 3 Project Divestment_signed.pdf'],
            ['5. Transfer and Collection', 'Transfer documents, payment evidence, receipts, and closing records', 'SPG SOI-03 Project Divestment', false, 'internal', 'internal_only', 'divestment', null, true, 'SPG/SOI/SOI - 3 Project Divestment_signed.pdf'],
        ];
    }

    private function taskRecords(): array
    {
        $now = now();
        $records = [];

        foreach ($this->spgNdcOwnedTasks() as $task) {
            $records[] = $this->taskRecord($task, $now);
        }

        foreach ($this->spgJvTasks() as $task) {
            $records[] = $this->taskRecord($task, $now);
        }

        foreach ($this->monitoringTasks() as $task) {
            $records[] = $this->taskRecord($task, $now);
        }

        foreach ($this->divestmentTasks() as $task) {
            $records[] = $this->taskRecord($task, $now);
        }

        foreach (['bdg_investment', 'spg_traditional'] as $track) {
            foreach ($this->investmentTasks($track) as $task) {
                $records[] = $this->taskRecord($task, $now);
            }
        }

        return $records;
    }

    private function taskRecord(array $task, $now): array
    {
        return [
            'track' => $task[0],
            'title' => $task[1],
            'description' => $task[2],
            'task_type' => $task[3],
            'soi_section' => $task[4],
            'assigned_role' => $task[5],
            'days' => $task[6],
            'priority' => $task[7],
            'is_milestone' => (bool) $task[8],
            'parent_task_title' => $task[9],
            'sort_order' => $task[10],
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function investmentTasks(string $track): array
    {
        return [
            [$track, '1. Eligibility screening and intake', 'Collect initial BDG/SPG checklist items and confirm basic eligibility.', 'intake', 'intake', 'Project Officer', 10, 'critical', true, null, 10],
            [$track, 'Receive brochure, pitch deck, and website/product page', null, 'intake', 'intake', 'Proponent', 5, 'critical', false, '1. Eligibility screening and intake', 11],
            [$track, 'Check Philippine registration, operating history, government accountabilities, funding declarations, and minimum investment size', null, 'intake', 'intake', 'Project Officer', 8, 'critical', false, '1. Eligibility screening and intake', 12],
            [$track, 'Confirm at least three NDC investment criteria', null, 'intake', 'intake', 'Project Officer', 10, 'urgent', false, '1. Eligibility screening and intake', 13],

            [$track, '2. Preliminary requirements and response letter', 'Receive LOI/proposal, authority documents, NDA or data privacy consent when required, then issue the NDC response/checklist.', 'requirements', 'requirements', 'Project Officer', 25, 'urgent', true, null, 20],
            [$track, 'Receive formal proposal letter or LOI', null, 'requirements', 'requirements', 'Proponent', 14, 'urgent', false, '2. Preliminary requirements and response letter', 21],
            [$track, 'Receive NDA, Secretary Certificate, and Data Privacy Consent when required', null, 'requirements', 'requirements', 'Proponent', 18, 'high', false, '2. Preliminary requirements and response letter', 22],
            [$track, 'Issue NDC response letter and documentary checklist', null, 'requirements', 'requirements', 'Project Officer', 25, 'urgent', false, '2. Preliminary requirements and response letter', 23],

            [$track, '3. Proposal and documentary requirements', 'Complete project details, corporate/legal/financial documents, proposal summary, and official checklist items.', 'requirements', 'requirements', 'Project Officer', 40, 'high', true, null, 30],
            [$track, 'Validate project description, location, market, beneficiaries, benefits, cost, revenue, NDC participation, and schedule', null, 'requirements', 'requirements', 'Project Officer', 32, 'high', false, '3. Proposal and documentary requirements', 31],
            [$track, 'Validate proponent background, shareholders, affiliates, previous projects, and management team', null, 'requirements', 'requirements', 'Project Officer', 35, 'high', false, '3. Proposal and documentary requirements', 32],
            [$track, 'Receive SEC/DTI, Articles, By-Laws, audited FS, BIR, tax clearance, licenses, permits, and supporting documents', null, 'requirements', 'requirements', 'Proponent', 40, 'high', false, '3. Proposal and documentary requirements', 33],

            [$track, '4. Feasibility study and due diligence', 'Evaluate the feasibility study/business plan, financial model, risk analysis, ESG/GAD items, valuation, and CBI.', 'due_diligence', 'due_diligence', 'Project Officer', 65, 'high', true, null, 40],
            [$track, 'Review feasibility study or business plan against NDC required format', null, 'due_diligence', 'due_diligence', 'Project Officer', 50, 'high', false, '4. Feasibility study and due diligence', 41],
            [$track, 'Review financial projections, return metrics, cost breakdown, financing structure, and use of proceeds', null, 'due_diligence', 'due_diligence', 'Project Officer', 58, 'high', false, '4. Feasibility study and due diligence', 42],
            [$track, 'Complete risk, ESG/GAD, company valuation, and credit/background investigation checks when applicable', null, 'due_diligence', 'due_diligence', 'Project Officer', 65, 'high', false, '4. Feasibility study and due diligence', 43],

            [$track, '5. Management and Board evaluation', 'Route approved proposals through IC when applicable, ManCom, and Board evaluation.', 'approval', 'management_review', 'Workgroup Head', 90, 'high', true, null, 50],
            [$track, 'Prepare initial evaluation and recommendation', null, 'approval', 'management_review', 'Project Officer', 72, 'high', false, '5. Management and Board evaluation', 51],
            [$track, 'Prepare Investment Committee evaluation for SVF when applicable', null, 'approval', 'management_review', 'Workgroup Head', 78, 'high', false, '5. Management and Board evaluation', 52],
            [$track, 'Prepare ManCom decision paper and record decision', null, 'approval', 'management_review', 'Workgroup Head', 82, 'high', false, '5. Management and Board evaluation', 53],
            [$track, 'Prepare Board package, approval record, conditions, or Secretary Certificate', null, 'approval', 'board_approval', 'Workgroup Head', 90, 'high', false, '5. Management and Board evaluation', 54],

            [$track, '6. Fund deployment', 'Complete agreement/contract documentation and record fund release evidence.', 'fund_release', 'agreement_fund_release', 'Project Officer', 110, 'medium', true, null, 60],
            [$track, 'Prepare Investment Agreement or Contract', null, 'fund_release', 'agreement_fund_release', 'Project Officer', 100, 'medium', false, '6. Fund deployment', 61],
            [$track, 'Coordinate legal, finance, compliance, and signatures', null, 'fund_release', 'agreement_fund_release', 'Project Officer', 105, 'medium', false, '6. Fund deployment', 62],
            [$track, 'Attach receipt issued by investee company or fund release evidence', null, 'fund_release', 'agreement_fund_release', 'Project Officer', 110, 'medium', false, '6. Fund deployment', 63],

            [$track, '7. Implementation monitoring and quarterly reporting', 'Maintain project summary, milestones, chronology, jobs generated, issues, next steps, and quarterly reports.', 'monitoring', 'implementation_monitoring', 'Project Officer', 140, 'medium', false, null, 70],
            [$track, 'Update project summary sheet, milestone schedule, chronology, and current status', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 122, 'medium', false, '7. Implementation monitoring and quarterly reporting', 71],
            [$track, 'Record jobs generated and financial/reporting indicators', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 130, 'medium', false, '7. Implementation monitoring and quarterly reporting', 72],
            [$track, 'Prepare quarterly COA or management update with issues and next steps', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 140, 'medium', false, '7. Implementation monitoring and quarterly reporting', 73],
        ];
    }

    private function spgNdcOwnedTasks(): array
    {
        return [
            ['spg_ndc_own', '1. Project concept and ManCom instruction', 'Prepare the NDC-owned project concept and secure ManCom approval to proceed.', 'intake', 'intake', 'Project Officer', 150, 'critical', true, null, 10],
            ['spg_ndc_own', 'Prepare project concept from ManCom instruction', null, 'intake', 'intake', 'Project Officer', 90, 'critical', false, '1. Project concept and ManCom instruction', 11],
            ['spg_ndc_own', 'Present project concept to ManCom for decision', null, 'intake', 'management_review', 'Workgroup Head', 150, 'urgent', false, '1. Project concept and ManCom instruction', 12],
            ['spg_ndc_own', 'Record ManCom directives and required revisions', null, 'intake', 'management_review', 'Project Officer', 150, 'high', false, '1. Project concept and ManCom instruction', 13],
            ['spg_ndc_own', '2. Consultancy procurement and study', 'Prepare TOR/MR, procure consultancy services, and complete the required study.', 'due_diligence', 'due_diligence', 'Project Officer', 547, 'high', true, null, 20],
            ['spg_ndc_own', 'Prepare TOR and budget estimates for consultancy services', null, 'due_diligence', 'due_diligence', 'Project Officer', 240, 'high', false, '2. Consultancy procurement and study', 21],
            ['spg_ndc_own', 'Prepare and approve Materials Requisition', null, 'due_diligence', 'due_diligence', 'Project Officer', 247, 'high', false, '2. Consultancy procurement and study', 22],
            ['spg_ndc_own', 'Conduct public bidding for consultancy services', null, 'due_diligence', 'due_diligence', 'Project Officer', 427, 'high', false, '2. Consultancy procurement and study', 23],
            ['spg_ndc_own', 'Complete study from NTP to final report', null, 'due_diligence', 'due_diligence', 'Project Officer', 547, 'high', false, '2. Consultancy procurement and study', 24],
            ['spg_ndc_own', '3. Study evaluation and management decision', 'Evaluate the study, prepare recommendations, and present the proposed project to ManCom.', 'approval', 'management_review', 'Workgroup Head', 610, 'high', true, null, 30],
            ['spg_ndc_own', 'Evaluate study and prepare presentation/recommendation', null, 'approval', 'management_review', 'Project Officer', 577, 'high', false, '3. Study evaluation and management decision', 31],
            ['spg_ndc_own', 'Present proposed project for ManCom approval and Board endorsement', null, 'approval', 'management_review', 'Workgroup Head', 607, 'high', false, '3. Study evaluation and management decision', 32],
            ['spg_ndc_own', '4. Board approval', 'Secure Board approval before detailed engineering and construction procurement.', 'approval', 'board_approval', 'Workgroup Head', 640, 'high', true, null, 40],
            ['spg_ndc_own', 'Present proposed project to Board of Directors', null, 'approval', 'board_approval', 'Workgroup Head', 640, 'high', false, '4. Board approval', 41],
            ['spg_ndc_own', 'Record Board approval, resolution, or Secretary Certificate', null, 'approval', 'board_approval', 'Workgroup Head', 640, 'high', false, '4. Board approval', 42],
            ['spg_ndc_own', '5. DED and construction procurement', 'Procure DED, conduct construction bidding, and sign construction agreement.', 'fund_release', 'agreement_fund_release', 'Project Officer', 920, 'high', true, null, 50],
            ['spg_ndc_own', 'Prepare TOR and MR for DED public bidding', null, 'fund_release', 'agreement_fund_release', 'Project Officer', 730, 'high', false, '5. DED and construction procurement', 51],
            ['spg_ndc_own', 'Conduct public bidding and complete DED', null, 'fund_release', 'agreement_fund_release', 'Project Officer', 850, 'high', false, '5. DED and construction procurement', 52],
            ['spg_ndc_own', 'Conduct construction bidding and execute construction agreement', null, 'fund_release', 'agreement_fund_release', 'Project Officer', 920, 'high', false, '5. DED and construction procurement', 53],
            ['spg_ndc_own', '6. Construction implementation and turn-over', 'Monitor construction implementation and record completion, acceptance, and turn-over.', 'implementation', 'implementation_monitoring', 'Project Officer', 1100, 'medium', false, null, 60],
            ['spg_ndc_own', 'Start construction monitoring after signed agreement and conditions', null, 'implementation', 'implementation_monitoring', 'Project Officer', 950, 'medium', false, '6. Construction implementation and turn-over', 61],
            ['spg_ndc_own', 'Track contractor completion and NDC acceptance', null, 'implementation', 'implementation_monitoring', 'Project Officer', 1080, 'medium', false, '6. Construction implementation and turn-over', 62],
            ['spg_ndc_own', 'Record turn-over and operations/maintenance handoff', null, 'implementation', 'implementation_monitoring', 'Project Officer', 1100, 'medium', false, '6. Construction implementation and turn-over', 63],
        ];
    }

    private function spgJvTasks(): array
    {
        return [
            ['spg_jv', '1. JV concept and ManCom approval to proceed', 'Conceptualize the JV project and secure ManCom approval to proceed with study and budget allocation.', 'intake', 'intake', 'Project Officer', 150, 'critical', true, null, 10],
            ['spg_jv', 'Prepare JV project concept', null, 'intake', 'intake', 'Project Officer', 90, 'critical', false, '1. JV concept and ManCom approval to proceed', 11],
            ['spg_jv', 'Present JV concept to ManCom for approval to proceed', null, 'intake', 'management_review', 'Workgroup Head', 150, 'urgent', false, '1. JV concept and ManCom approval to proceed', 12],
            ['spg_jv', 'Record ManCom revisions, deferral, or approval', null, 'intake', 'management_review', 'Project Officer', 150, 'high', false, '1. JV concept and ManCom approval to proceed', 13],
            ['spg_jv', '2. Consultancy procurement and study', 'Procure consultancy services, conduct the study, and prepare the study report.', 'due_diligence', 'due_diligence', 'Project Officer', 547, 'high', true, null, 20],
            ['spg_jv', 'Prepare TOR, budget estimates, and Materials Requisition', null, 'due_diligence', 'due_diligence', 'Project Officer', 247, 'high', false, '2. Consultancy procurement and study', 21],
            ['spg_jv', 'Conduct public bidding for consultancy services', null, 'due_diligence', 'due_diligence', 'Project Officer', 427, 'high', false, '2. Consultancy procurement and study', 22],
            ['spg_jv', 'Complete study report from NTP', null, 'due_diligence', 'due_diligence', 'Project Officer', 547, 'high', false, '2. Consultancy procurement and study', 23],
            ['spg_jv', '3. ManCom and Board approval of JV project', 'Evaluate the study, present to ManCom, and secure Board approval before NEDA-ICC requirements.', 'approval', 'management_review', 'Workgroup Head', 640, 'high', true, null, 30],
            ['spg_jv', 'Evaluate study and prepare recommendation', null, 'approval', 'management_review', 'Project Officer', 577, 'high', false, '3. ManCom and Board approval of JV project', 31],
            ['spg_jv', 'Present JV project to ManCom for decision', null, 'approval', 'management_review', 'Workgroup Head', 607, 'high', false, '3. ManCom and Board approval of JV project', 32],
            ['spg_jv', 'Present JV project to Board of Directors', null, 'approval', 'board_approval', 'Workgroup Head', 640, 'high', false, '3. ManCom and Board approval of JV project', 33],
            ['spg_jv', '4. NEDA-ICC approval and JV-SC composition', 'Obtain required NEDA-ICC documents, submit the JV proposal, and secure Board approval of final JVA terms and JV-SC.', 'approval', 'board_approval', 'Project Officer', 760, 'high', true, null, 40],
            ['spg_jv', 'Obtain NEDA-ICC requirements and endorsements if required', null, 'approval', 'board_approval', 'Project Officer', 690, 'high', false, '4. NEDA-ICC approval and JV-SC composition', 41],
            ['spg_jv', 'Prepare and submit JV proposal to NEDA-ICC', null, 'approval', 'board_approval', 'Project Officer', 720, 'high', false, '4. NEDA-ICC approval and JV-SC composition', 42],
            ['spg_jv', 'Record NEDA-ICC approval and Board approval of JVA terms/JV-SC', null, 'approval', 'board_approval', 'Workgroup Head', 760, 'high', false, '4. NEDA-ICC approval and JV-SC composition', 43],
            ['spg_jv', '5. JV partner selection, award, and JVA signing', 'Prepare JV selection documents, conduct selection, secure final Board award, issue NOA, and sign the JVA.', 'fund_release', 'agreement_fund_release', 'Workgroup Head', 1010, 'high', true, null, 50],
            ['spg_jv', 'Prepare JV selection documents', null, 'fund_release', 'agreement_fund_release', 'Project Officer', 820, 'high', false, '5. JV partner selection, award, and JVA signing', 51],
            ['spg_jv', 'Conduct JV partner selection through JV-SC', null, 'fund_release', 'agreement_fund_release', 'Workgroup Head', 1000, 'high', false, '5. JV partner selection, award, and JVA signing', 52],
            ['spg_jv', 'Secure Board approval and issue NOA to winning participant', null, 'fund_release', 'agreement_fund_release', 'Workgroup Head', 1007, 'high', false, '5. JV partner selection, award, and JVA signing', 53],
            ['spg_jv', 'Sign JVA within ten days after NOA', null, 'fund_release', 'agreement_fund_release', 'Workgroup Head', 1010, 'high', false, '5. JV partner selection, award, and JVA signing', 54],
        ];
    }

    private function monitoringTasks(): array
    {
        return [
            ['implementation_monitoring', '1. Consolidation of milestones and targets', 'Create the project/account summary folder from signed documents, fund release records, covenants, and milestones.', 'monitoring', 'implementation_monitoring', 'Project Officer', 14, 'high', true, null, 10],
            ['implementation_monitoring', 'Attach signed documents and release-of-funds records', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 7, 'high', false, '1. Consolidation of milestones and targets', 11],
            ['implementation_monitoring', 'Prepare project/account summary sheet', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 10, 'high', false, '1. Consolidation of milestones and targets', 12],
            ['implementation_monitoring', 'List implementation milestones, chronology, status, issues, and next steps', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 14, 'high', false, '1. Consolidation of milestones and targets', 13],
            ['implementation_monitoring', '2. Monitoring and management', 'Monitor milestones, financial performance, covenants, correspondence, risks, jobs generated, and issues.', 'monitoring', 'implementation_monitoring', 'Project Officer', 45, 'medium', false, null, 20],
            ['implementation_monitoring', 'Update milestone, covenant, and risk schedule', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 21, 'medium', false, '2. Monitoring and management', 21],
            ['implementation_monitoring', 'Upload correspondence, statement of account, financial statements, and Board papers', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 30, 'medium', false, '2. Monitoring and management', 22],
            ['implementation_monitoring', 'Record jobs generated and quarterly management/COA notes', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 45, 'medium', false, '2. Monitoring and management', 23],
            ['implementation_monitoring', '3. Adjustment or modification decision', 'Route restructuring, equity changes, divestment, or loan-to-equity conversion issues to ManCom and Board when needed.', 'approval', 'implementation_monitoring', 'Workgroup Head', 75, 'medium', true, null, 30],
            ['implementation_monitoring', 'Prepare background, updates, issues, options, and recommendations', null, 'approval', 'implementation_monitoring', 'Project Officer', 60, 'medium', false, '3. Adjustment or modification decision', 31],
            ['implementation_monitoring', 'Present adjustment or modification issue to ManCom', null, 'approval', 'implementation_monitoring', 'Workgroup Head', 68, 'high', false, '3. Adjustment or modification decision', 32],
            ['implementation_monitoring', 'Record Board decision and update agreement/MOA if required', null, 'approval', 'implementation_monitoring', 'Workgroup Head', 75, 'high', false, '3. Adjustment or modification decision', 33],
            ['implementation_monitoring', '4. Post-investment strategy review', 'Review redemption, conversion, restructuring, share transfer, dividend, or exit options.', 'post_investment', 'post_investment_strategy', 'Workgroup Head', 105, 'medium', true, null, 40],
            ['implementation_monitoring', 'Review maturing notes, equity holdings, and restructuring options', null, 'post_investment', 'post_investment_strategy', 'Project Officer', 90, 'medium', false, '4. Post-investment strategy review', 41],
            ['implementation_monitoring', 'Recommend post-investment strategy to Management', null, 'post_investment', 'post_investment_strategy', 'Workgroup Head', 100, 'medium', false, '4. Post-investment strategy review', 42],
            ['implementation_monitoring', 'Attach transfer receipts or term sheets when applicable', null, 'post_investment', 'post_investment_strategy', 'Project Officer', 105, 'medium', false, '4. Post-investment strategy review', 43],
        ];
    }

    private function divestmentTasks(): array
    {
        return [
            ['divestment', '1. Start divestment and due diligence', 'Begin divestment proceedings and complete legal and financial due diligence.', 'divestment', 'divestment', 'Legal and Finance', 30, 'high', true, null, 10],
            ['divestment', 'Record ManCom-approved divestment recommendation or external offer', null, 'divestment', 'divestment', 'Legal and Finance', 7, 'high', false, '1. Start divestment and due diligence', 11],
            ['divestment', 'Complete legal due diligence and legal memo', null, 'divestment', 'divestment', 'Legal and Finance', 20, 'high', false, '1. Start divestment and due diligence', 12],
            ['divestment', 'Complete financial due diligence, asset appraisal, and pricing basis', null, 'divestment', 'divestment', 'Legal and Finance', 30, 'high', false, '1. Start divestment and due diligence', 13],
            ['divestment', '2. ManCom approval of divestment terms', 'Prepare proposed terms and conditions of share or asset transfer for ManCom approval.', 'approval', 'divestment', 'ManCom', 45, 'high', true, null, 20],
            ['divestment', 'Draft terms and conditions of transfer with Legal and Finance', null, 'approval', 'divestment', 'Legal and Finance', 38, 'high', false, '2. ManCom approval of divestment terms', 21],
            ['divestment', 'Prepare ManCom paper and presentation', null, 'approval', 'divestment', 'Legal and Finance', 42, 'high', false, '2. ManCom approval of divestment terms', 22],
            ['divestment', 'Record ManCom decision and revisions if any', null, 'approval', 'divestment', 'ManCom', 45, 'high', false, '2. ManCom approval of divestment terms', 23],
            ['divestment', '3. Board approval of divestment', 'Secure Board decision on the terms and conditions of divestment.', 'approval', 'divestment', 'Board', 60, 'high', true, null, 30],
            ['divestment', 'Prepare Board paper and Secretary Certificate requirements', null, 'approval', 'divestment', 'Legal and Finance', 55, 'high', false, '3. Board approval of divestment', 31],
            ['divestment', 'Present divestment terms to Board of Directors', null, 'approval', 'divestment', 'Board', 60, 'high', false, '3. Board approval of divestment', 32],
            ['divestment', 'Record Board decision and required adjustments', null, 'approval', 'divestment', 'Board', 60, 'high', false, '3. Board approval of divestment', 33],
            ['divestment', '4. Execute divestment transfer and collection', 'Complete documentary requirements, payments, receipts, and transfer of shares/assets.', 'divestment', 'divestment', 'Legal and Finance', 90, 'high', true, null, 40],
            ['divestment', 'Prepare and sign transfer documents', null, 'divestment', 'divestment', 'Legal and Finance', 75, 'high', false, '4. Execute divestment transfer and collection', 41],
            ['divestment', 'Collect payments and issue receipts', null, 'divestment', 'divestment', 'Legal and Finance', 82, 'high', false, '4. Execute divestment transfer and collection', 42],
            ['divestment', 'Record final transfer of shares/assets and close divestment file', null, 'divestment', 'divestment', 'Legal and Finance', 90, 'high', false, '4. Execute divestment transfer and collection', 43],
        ];
    }
}
