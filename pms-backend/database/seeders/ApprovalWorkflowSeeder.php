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
            ['name' => 'ManCom', 'description' => 'Management Committee member'],
            ['name' => 'Board', 'description' => 'Board member'],
        ];

        foreach ($requiredRoles as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name']],
                [
                    'description' => $role['description'],
                    'is_system_role' => true,
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }

        $workflowData = [
            'name' => 'SOI Sequential Approval',
            'description' => 'Sequential routing: Proponent -> Project Officer -> Workgroup Head -> ManCom -> Board',
            'project_type_id' => null, // applies to all types
            'is_active' => true,
            'created_at' => now(),
        ];

        DB::table('approval_workflows')->updateOrInsert(
            ['name' => $workflowData['name']],
            $workflowData
        );

        $workflowId = DB::table('approval_workflows')
            ->where('name', $workflowData['name'])
            ->value('id');

        $roles = DB::table('roles')->pluck('id', 'name');

        $steps = [
            ['step_order' => 1, 'role_name' => 'Proponent', 'step_name' => 'Proponent Submission'],
            ['step_order' => 2, 'role_name' => 'Project Officer', 'step_name' => 'Project Officer Evaluation'],
            ['step_order' => 3, 'role_name' => 'Workgroup Head', 'step_name' => 'Workgroup Head Approval'],
            ['step_order' => 4, 'role_name' => 'ManCom', 'step_name' => 'ManCom Approval'],
            ['step_order' => 5, 'role_name' => 'Board', 'step_name' => 'Board Approval'],
        ];

        foreach ($steps as $step) {
            DB::table('approval_steps')->updateOrInsert(
                [
                    'workflow_id' => $workflowId,
                    'step_order' => $step['step_order'],
                ],
                [
                    'role_id' => $roles[$step['role_name']],
                    'step_name' => $step['step_name'],
                    'is_required' => true,
                    'can_skip' => false,
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }

        // Keep SOI workflow as the single active default.
        DB::table('approval_workflows')
            ->where('name', '!=', $workflowData['name'])
            ->update(['is_active' => false]);
    }
}
