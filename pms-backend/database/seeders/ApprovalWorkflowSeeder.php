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
                'name' => 'NDC Standard Investment Approval',
                'description' => 'BDG/SPG SOI routing: Proponent -> AO completeness -> due diligence -> AGM/Workgroup -> ManCom -> Board -> agreement and fund release readiness.',
                'steps' => [
                    [1, 'Proponent', 'Proponent Submission'],
                    [2, 'Project Officer', 'Account Officer Completeness Check'],
                    [3, 'Project Officer', 'Validation, Triangulation, and Due Diligence'],
                    [4, 'Workgroup Head', 'AGM / Workgroup Review'],
                    [5, 'ManCom', 'ManCom Decision'],
                    [6, 'Board', 'Board Approval'],
                    [7, 'Legal and Finance', 'Agreement Signing and Fund Release Readiness'],
                ],
            ],
            [
                'name' => 'NDC SVF Investment Approval',
                'description' => 'SVF routing with Investment Committee evaluation before ManCom and Board action.',
                'steps' => [
                    [1, 'Proponent', 'Proponent Submission'],
                    [2, 'Project Officer', 'Account Officer Completeness Check'],
                    [3, 'Project Officer', 'Validation, Triangulation, and Due Diligence'],
                    [4, 'Investment Committee', 'Investment Committee Evaluation'],
                    [5, 'Workgroup Head', 'AGM / Workgroup Endorsement'],
                    [6, 'ManCom', 'ManCom Decision'],
                    [7, 'Board', 'Board Approval'],
                    [8, 'Legal and Finance', 'Agreement Signing and Fund Release Readiness'],
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

            foreach ($workflow['steps'] as [$order, $roleName, $stepName]) {
                DB::table('approval_steps')->updateOrInsert(
                    [
                        'workflow_id' => $workflowId,
                        'step_order' => $order,
                    ],
                    [
                        'role_id' => $roles[$roleName],
                        'step_name' => $stepName,
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
}
