<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $categories = [
            'bdg_investment' => ['origin', 'Traditional / External Investment', null],
            'bdg_svf' => ['origin', 'Startup Venture', null],
            'spg_jv' => ['origin', 'Joint Venture', null],
            'spg_ndc_own' => ['origin', 'NDC-Initiated', null],
            'spg_traditional' => ['legacy', 'Traditional / External Investment (Legacy SPG Route)', null],
        ];

        foreach ($categories as $workflowKey => [$group, $displayName, $parentId]) {
            DB::table('approval_workflows')->where('workflow_key', $workflowKey)->update([
                'workflow_group' => $group,
                'display_name' => $displayName,
                'parent_workflow_id' => $parentId,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('approval_workflows')->where('workflow_key', 'bdg_investment')->update([
            'workflow_group' => 'origin',
            'display_name' => 'External Investment Proposal (BDG)',
        ]);
        DB::table('approval_workflows')->where('workflow_key', 'bdg_svf')->update([
            'workflow_group' => 'variant',
            'display_name' => 'Startup Venture Fund',
            'parent_workflow_id' => DB::table('approval_workflows')->where('workflow_key', 'bdg_investment')->value('id'),
        ]);
        DB::table('approval_workflows')->where('workflow_key', 'spg_jv')->update([
            'workflow_group' => 'origin',
            'display_name' => 'Joint Venture Proposal (SPG)',
        ]);
        DB::table('approval_workflows')->where('workflow_key', 'spg_traditional')->update([
            'workflow_group' => 'origin',
            'display_name' => 'Traditional Equity Funding (SPG)',
        ]);
        DB::table('approval_workflows')->where('workflow_key', 'spg_ndc_own')->update([
            'workflow_group' => 'origin',
            'display_name' => 'NDC-Owned Project (SPG)',
        ]);
    }
};
