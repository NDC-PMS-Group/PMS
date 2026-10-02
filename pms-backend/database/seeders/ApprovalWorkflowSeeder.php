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
            ['name' => 'Legal', 'description' => 'Agreement drafting, legal due diligence, and contract review'],
            ['name' => 'Finance', 'description' => 'Fund release, financial due diligence, collections, and remittance review'],
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
                    [8, 'Legal', 'Legal Agreement Drafting and Signing Readiness'],
                    [9, 'Finance', 'Finance Fund Release Readiness'],
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
                    [9, 'Legal', 'Legal Agreement Drafting and Signing Readiness'],
                    [10, 'Finance', 'Finance Fund Release Readiness'],
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
                    [8, 'Legal', 'Legal Agreement Drafting and Signing', 'agreement_fund_release'],
                    [9, 'Finance', 'Finance Fund Release Readiness', 'agreement_fund_release'],
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
                    [6, 'Legal', 'DED / Construction Agreement Review', 'agreement_fund_release'],
                    [7, 'Finance', 'Construction Procurement and Fund Readiness', 'agreement_fund_release'],
                    [8, 'Project Officer', 'Construction Implementation and Turn-over', 'implementation_monitoring'],
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
                    [10, 'Legal', 'Signing of JVA', 'agreement_fund_release'],
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
                    [1, 'Legal', 'Divestment Legal Due Diligence'],
                    [2, 'Finance', 'Divestment Financial Due Diligence and Valuation Basis'],
                    [3, 'ManCom', 'ManCom Approval of Divestment Terms'],
                    [4, 'Board', 'Board Approval of Divestment'],
                    [5, 'Legal', 'Execute Divestment Transfer Documents'],
                    [6, 'Finance', 'Collect Payments and Issue Receipts'],
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
                        'requires_agreement_form' => $this->requiresAgreementForm($roleName, $stepName, $soiSection),
                        'is_required' => true,
                        'can_skip' => false,
                        'created_at' => now(),
                    ]
                );
            }
        }

        $catalog = [
            'NDC BDG Investment Approval' => ['bdg_investment', 'origin', 'Traditional / External Investment', null, null, ['internal', 'proponent']],
            'NDC SVF Investment Approval' => ['bdg_svf', 'origin', 'Startup Venture', null, null, ['internal', 'proponent']],
            'SPG Joint Venture Project Approval' => ['spg_jv', 'origin', 'Joint Venture', null, null, ['internal', 'proponent']],
            'SPG Traditional Equity Funding Approval' => ['spg_traditional', 'legacy', 'Traditional / External Investment (Legacy SPG Route)', null, null, ['internal']],
            'SPG NDC-Owned Project Approval' => ['spg_ndc_own', 'origin', 'NDC-Initiated', null, null, ['internal']],
            'NDC Implementation and Monitoring Workflow' => ['implementation_monitoring', 'lifecycle', 'Implementation & Monitoring', null, 'start_implementation', ['internal']],
            'NDC Divestment Approval' => ['divestment', 'lifecycle', 'Divestment / Exit', null, 'open_divestment_case', ['internal']],
        ];

        foreach ($catalog as $name => [$key, $group, $displayName, $parentKey, $entryAction, $audiences]) {
            $workflow = DB::table('approval_workflows')->where('name', $name)->first();
            if (! $workflow) {
                continue;
            }

            DB::table('approval_workflows')->where('id', $workflow->id)->update([
                'workflow_key' => $workflow->workflow_key ?: $key,
                'workflow_group' => $group,
                'display_name' => $displayName,
                'entry_action' => $workflow->entry_action ?: $entryAction,
                'audiences' => $workflow->audiences ?: json_encode($audiences),
            ]);
        }

        foreach ($catalog as $name => [, , , $parentKey]) {
            DB::table('approval_workflows')->where('name', $name)->update([
                'parent_workflow_id' => $parentKey
                    ? DB::table('approval_workflows')->where('workflow_key', $parentKey)->value('id')
                    : null,
            ]);
        }

        // Keep legacy workflow inactive, but do not delete old history.
        DB::table('approval_workflows')
            ->whereNull('workflow_key')
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

    private function requiresAgreementForm(string $roleName, string $stepName, ?string $soiSection): bool
    {
        $role = strtolower($roleName);
        if (!str_contains($role, 'legal')) {
            return false;
        }

        $text = strtolower($stepName . ' ' . ($soiSection ?? ''));

        return str_contains($text, 'agreement')
            || str_contains($text, 'jva')
            || str_contains($text, 'contract')
            || str_contains($text, 'signing')
            || str_contains($text, 'construction agreement');
    }
}
