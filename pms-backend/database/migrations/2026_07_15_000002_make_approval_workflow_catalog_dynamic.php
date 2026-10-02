<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_workflows', function (Blueprint $table) {
            $table->string('workflow_key', 80)->nullable()->unique()->after('id');
            $table->string('workflow_group', 30)->nullable()->index()->after('workflow_key');
            $table->string('display_name', 120)->nullable()->after('name');
            $table->foreignId('parent_workflow_id')->nullable()->after('project_type_id')
                ->constrained('approval_workflows')->nullOnDelete();
            $table->string('entry_action', 80)->nullable()->after('parent_workflow_id');
            $table->json('audiences')->nullable()->after('entry_action');
        });

        $catalog = [
            'NDC BDG Investment Approval' => ['bdg_investment', 'origin', 'External Investment Proposal (BDG)', null, null, ['internal', 'proponent']],
            'NDC SVF Investment Approval' => ['bdg_svf', 'variant', 'Startup Venture Fund', 'bdg_investment', null, ['internal', 'proponent']],
            'SPG Joint Venture Project Approval' => ['spg_jv', 'origin', 'Joint Venture Proposal (SPG)', null, null, ['internal', 'proponent']],
            'SPG Traditional Equity Funding Approval' => ['spg_traditional', 'origin', 'Traditional Equity Funding (SPG)', null, null, ['internal']],
            'SPG NDC-Owned Project Approval' => ['spg_ndc_own', 'origin', 'NDC-Owned Project (SPG)', null, null, ['internal']],
            'NDC Implementation and Monitoring Workflow' => ['implementation_monitoring', 'lifecycle', 'Implementation & Monitoring', null, 'start_implementation', ['internal']],
            'NDC Divestment Approval' => ['divestment', 'lifecycle', 'Divestment / Exit', null, 'open_divestment_case', ['internal']],
        ];

        foreach ($catalog as $name => [$key, $group, $displayName, $parentKey, $entryAction, $audiences]) {
            $workflowId = DB::table('approval_workflows')
                ->where('name', $name)
                ->orderByDesc('is_active')
                ->orderBy('id')
                ->value('id');

            if (! $workflowId) {
                continue;
            }

            DB::table('approval_workflows')->where('id', $workflowId)->update([
                'workflow_key' => $key,
                'workflow_group' => $group,
                'display_name' => $displayName,
                'entry_action' => $entryAction,
                'audiences' => json_encode($audiences),
            ]);

            if ($parentKey) {
                DB::table('approval_workflows')->where('id', $workflowId)->update([
                    'parent_workflow_id' => DB::table('approval_workflows')
                        ->where('workflow_key', $parentKey)
                        ->value('id'),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('approval_workflows', function (Blueprint $table) {
            $table->dropForeign(['parent_workflow_id']);
            $table->dropUnique(['workflow_key']);
            $table->dropIndex(['workflow_group']);
            $table->dropColumn([
                'workflow_key',
                'workflow_group',
                'display_name',
                'parent_workflow_id',
                'entry_action',
                'audiences',
            ]);
        });
    }
};
