<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApprovalWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles required by routing exist.
        $requiredRoles = [
            ['name' => 'Proponent', 'description' => 'Project proponent / originator'],
            ['name' => 'Project Officer', 'description' => 'Manages assigned projects'],
            ['name' => 'Workgroup Head', 'description' => 'Heads a workgroup'],
            ['name' => 'Investment Committee', 'description' => 'SVF Investment Committee evaluator'],
            ['name' => 'ManCom', 'description' => 'Management Committee member'],
            ['name' => 'Board', 'description' => 'Board member'],
            ['name' => 'Legal and Finance', 'description' => 'Agreement, compliance, and fund release reviewer'],
        ];

        foreach ($requiredRoles as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name']],
                [
                    'description' => $role['description'],
                    'is_system_role' => true,
                    'created_at' => now(),
                ]
            );
        }

        $workflows = [
            [
                'name' => 'NDC BDG Investment Approval',
                'description' => 'BDG SOI-01 routing for external investment proposals: intake/KYC, requirements, due diligence, AGM/Workgroup, ManCom, Board, then agreement and fund release.',
                'steps' => [
                    [1, 'Proponent', 'Proponent Submission'],
                    [2, 'Project Officer', 'Pre-screening / KYC and LOI Receipt'],
                    [3, 'Project Officer', 'Response Letter and Completeness Check'],
                    [4, 'Project Officer', 'Validation, Triangulation, and Due Diligence'],
                    [5, 'Workgroup Head', 'AGM / Workgroup Review'],
                    [6, 'ManCom', 'ManCom Decision'],
                    [7, 'Board', 'Board Approval'],
                    [8, 'Legal and Finance', 'Agreement Signing and Fund Release Readiness'],
                ],
            ],
            [
                'name' => 'NDC SVF Investment Approval',
                'description' => 'BDG SOI-01 SVF routing with Investment Committee evaluation before AGM/Workgroup, ManCom, and Board action.',
                'steps' => [
                    [1, 'Proponent', 'Proponent Submission'],
                    [2, 'Project Officer', 'Pre-screening / KYC and LOI Receipt'],
                    [3, 'Project Officer', 'Response Letter and Completeness Check'],
                    [4, 'Project Officer', 'Validation, Triangulation, and Due Diligence'],
                    [5, 'Investment Committee', 'Investment Committee Evaluation'],
                    [6, 'Workgroup Head', 'AGM / Workgroup Endorsement'],
                    [7, 'ManCom', 'ManCom Decision'],
                    [8, 'Board', 'Board Approval'],
                    [9, 'Legal and Finance', 'Agreement Signing and Fund Release Readiness'],
                ],
            ],
            [
                'name' => 'SPG Traditional Equity Funding Approval',
                'description' => 'SPG SOI-01 traditional equity funding route: LOI/concept, initial validation, requirements, ManCom, Board, agreement signing, and fund release.',
                'steps' => [
                    [1, 'Proponent', 'Proponent Submission', 'intake'],
                    [2, 'Project Officer', 'Receipt of LOI and Project Concept', 'intake'],
                    [3, 'Project Officer', 'Initial Review and Validation', 'due_diligence'],
                    [4, 'Project Officer', 'Response Letter and Requirements Check', 'requirements'],
                    [5, 'Project Officer', 'Validation and Triangulation of Complete Requirements', 'due_diligence'],
                    [6, 'ManCom', 'ManCom Decision', 'management_review'],
                    [7, 'Board', 'Board Approval', 'board_approval'],
                    [8, 'Legal and Finance', 'Agreement Signing and Fund Release', 'agreement_fund_release'],
                ],
            ],
            [
                'name' => 'SPG NDC-Owned Project Approval',
                'description' => 'SPG SOI-01 route for projects implemented by NDC on its own, including study procurement, ManCom/Board decisions, DED, construction, and turn-over.',
                'steps' => [
                    [1, 'Project Officer', 'Project Conceptualization', 'intake'],
                    [2, 'ManCom', 'ManCom Approval to Proceed', 'management_review'],
                    [3, 'Project Officer', 'Procurement of Consultancy Services and Conduct of Study', 'due_diligence'],
                    [4, 'ManCom', 'ManCom Project Decision', 'management_review'],
                    [5, 'Board', 'Board Approval', 'board_approval'],
                    [6, 'Legal and Finance', 'DED / Construction Procurement and Agreement', 'agreement_fund_release'],
                    [7, 'Project Officer', 'Construction Implementation and Turn-over', 'implementation_monitoring'],
                ],
            ],
            [
                'name' => 'SPG Joint Venture Project Approval',
                'description' => 'SPG SOI-01 JV route: concept, ManCom approval, study, Board approval, NEDA-ICC, JV-SC/selection, final Board award, and JVA signing.',
                'steps' => [
                    [1, 'Project Officer', 'JV Project Conceptualization', 'intake'],
                    [2, 'ManCom', 'ManCom Approval to Proceed', 'management_review'],
                    [3, 'Project Officer', 'Procurement of Consultancy Services and Conduct of Study', 'due_diligence'],
                    [4, 'ManCom', 'ManCom JV Project Decision', 'management_review'],
                    [5, 'Board', 'Board Approval of JV Project', 'board_approval'],
                    [6, 'Project Officer', 'NEDA-ICC Coordination and Approval', 'board_approval'],
                    [7, 'Board', 'Board Approval of NEDA-Approved JVA Terms and JV-SC', 'board_approval'],
                    [8, 'Workgroup Head', 'JV Partner Selection and Award', 'board_approval'],
                    [9, 'Board', 'Final Board Approval and Award', 'board_approval'],
                    [10, 'Legal and Finance', 'Signing of JVA', 'agreement_fund_release'],
                ],
            ],
            [
                'name' => 'NDC Implementation and Monitoring Workflow',
                'description' => 'BDG/SPG implementation and monitoring route after approval: milestones, monitoring, adjustment decisions, and post-investment review.',
                'steps' => [
                    [1, 'Project Officer', 'Consolidation of Milestones and Targets'],
                    [2, 'Workgroup Head', 'Setting of Milestones / Targets'],
                    [3, 'Project Officer', 'Monitoring and Management Update'],
                    [4, 'ManCom', 'ManCom / Board Endorsement for Adjustments if Required'],
                    [5, 'Workgroup Head', 'Post-Investment Strategy Review'],
                ],
            ],
            [
                'name' => 'NDC Divestment Approval',
                'description' => 'SPG SOI-03 divestment route: legal/financial due diligence, ManCom approval, Board approval, and execution of transfer/collection.',
                'steps' => [
                    [1, 'Legal and Finance', 'Divestment Legal and Financial Due Diligence'],
                    [2, 'ManCom', 'ManCom Approval of Divestment Terms'],
                    [3, 'Board', 'Board Approval of Divestment'],
                    [4, 'Legal and Finance', 'Execute Divestment Procedure and Transfer'],
                ],
            ],
        ];

        $roles = DB::table('roles')->pluck('id', 'name');

        foreach ($workflows as $workflow) {
            DB::table('approval_workflows')->updateOrInsert(
                ['name' => $workflow['name']],
                [
                    'name' => $workflow['name'],
                    'description' => $workflow['description'],
                    'project_type_id' => null,
                    'is_active' => true,
                    'created_at' => now(),
                ]
            );

            $workflowId = DB::table('approval_workflows')
                ->where('name', $workflow['name'])
                ->value('id');

            foreach ($workflow['steps'] as $step) {
                [$order, $roleName, $stepName] = $step;
                $soiSection = $step[3] ?? $this->deriveStepSoiSection($stepName);

                DB::table('approval_steps')->updateOrInsert(
                    [
                        'workflow_id' => $workflowId,
                        'step_order' => $order,
                    ],
                    [
                        'role_id' => $roles[$roleName],
                        'step_name' => $stepName,
                        'soi_section' => $soiSection,
                        'is_required' => true,
                        'can_skip' => false,
                        'created_at' => now(),
                    ]
                );
            }
        }

        // Keep legacy workflow inactive, but do not delete old history.
        DB::table('approval_workflows')
            ->whereNotIn('name', array_column($workflows, 'name'))
            ->update(['is_active' => false]);
    }

    private function deriveStepSoiSection(string $stepName): ?string
    {
        $name = strtolower($stepName);

        if (str_contains($name, 'divest')) return 'divestment';
        if (str_contains($name, 'post-investment') || str_contains($name, 'post investment')) return 'post_investment_strategy';
        if (str_contains($name, 'monitor') || str_contains($name, 'milestone') || str_contains($name, 'turn-over') || str_contains($name, 'turnover')) return 'implementation_monitoring';
        if (str_contains($name, 'agreement') || str_contains($name, 'fund release') || str_contains($name, 'jva') || str_contains($name, 'construction')) return 'agreement_fund_release';
        if (str_contains($name, 'board') || str_contains($name, 'neda') || str_contains($name, 'icc') || str_contains($name, 'selection and award')) return 'board_approval';
        if (str_contains($name, 'mancom') || str_contains($name, 'workgroup') || str_contains($name, 'agm')) return 'management_review';
        if (str_contains($name, 'due diligence') || str_contains($name, 'evaluation') || str_contains($name, 'validation') || str_contains($name, 'study')) return 'due_diligence';
        if (str_contains($name, 'requirement') || str_contains($name, 'completeness') || str_contains($name, 'checklist') || str_contains($name, 'response letter')) return 'requirements';
        if (str_contains($name, 'completion')) return 'completion';
        if (str_contains($name, 'submission') || str_contains($name, 'intake') || str_contains($name, 'concept') || str_contains($name, 'kyc') || str_contains($name, 'loi')) return 'intake';

        return null;
    }
}
