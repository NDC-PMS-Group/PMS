<?php

use App\Models\Project;
use App\Support\SoiDevelopmentProcedure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('project_statuses')->insertOrIgnore([
                'name' => 'Disapproved', 'color_code' => '#b91c1c', 'is_active' => true, 'created_at' => now(),
            ]);
            $headRole = DB::table('roles')->where('name', 'Workgroup Head')->value('id');
            foreach (['bdg_investment', 'spg_traditional', 'spg_ndc_own', 'spg_jv'] as $key) {
                $workflow = DB::table('approval_workflows')->where('workflow_key', $key)->first();
                if (!$workflow) {
                    continue;
                }
                DB::table('approval_workflows')->where('id', $workflow->id)->update([
                    'description' => SoiDevelopmentProcedure::SOURCE.($key === 'spg_jv'
                        ? ' section 9: follow the applicable latest DEPDev JV guidelines; retain the guideline/version and compliance evidence.'
                        : ': documentary qualification, recorded decisions, compliance and re-presentation before proceeding.'),
                ]);
                foreach (DB::table('approval_steps')->where('workflow_id', $workflow->id)->get() as $step) {
                    $values = ['step_name' => str_replace('NEDA', 'DEPDev', $step->step_name)];
                    if ($headRole && str_contains(strtolower($step->step_name), 'response letter')) {
                        $values['role_id'] = $headRole;
                    }
                    DB::table('approval_steps')->where('id', $step->id)->update($values);
                }
            }
            DB::table('project_statuses')->where('name', 'For NEDA-ICC Review')->update(['name' => 'For DEPDev-ICC Review']);
            foreach (['project_requirements' => ['group_name', 'item_name', 'source_document'], 'tasks' => ['title', 'description']] as $table => $fields) {
                foreach ($fields as $field) {
                    foreach (DB::table($table)->where($field, 'like', '%NEDA%')->get(['id', $field]) as $row) {
                        DB::table($table)->where('id', $row->id)->update([$field => str_replace('NEDA', 'DEPDev', $row->{$field})]);
                    }
                }
            }
            // Preserve project/requirement IDs, uploaded documents, decisions and completed work.
            Project::withTrashed()->where('is_svf', false)->orderBy('id')->chunkById(100, function ($projects) {
                foreach ($projects as $project) {
                    SoiDevelopmentProcedure::alignRequirements($project);
                }
            });
        });
    }

    public function down(): void
    {
        // Evidence/decisions may reference the new requirements; restore the pre-rollout DB backup
        // to undo policy metadata. Never delete submitted evidence during a migration rollback.
    }
};
