<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectTaskTemplateService;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tasks')
            ->where('task_scope', 'legacy_soi')
            ->where('archive_reason', 'Archived during implementation task alignment')
            ->update([
                'task_scope' => 'workflow',
                'workstream' => DB::raw("CASE soi_section
                    WHEN 'intake' THEN 'Intake'
                    WHEN 'requirements' THEN 'Requirements'
                    WHEN 'due_diligence' THEN 'Due Diligence'
                    WHEN 'management_review' THEN 'Management Review'
                    WHEN 'board_approval' THEN 'Board Approval'
                    WHEN 'agreement_fund_release' THEN 'Agreement & Fund Release'
                    WHEN 'implementation_monitoring' THEN 'Implementation & Monitoring'
                    WHEN 'post_investment_strategy' THEN 'Post-Investment Strategy'
                    WHEN 'divestment' THEN 'Divestment / Exit'
                    WHEN 'completion' THEN 'Completion'
                    ELSE COALESCE(workstream, 'Project Work') END"),
                'archived_at' => null,
                'archive_reason' => null,
                'updated_at' => now(),
            ]);

        $fallbackActor = User::query()->orderBy('id')->first();
        if ($fallbackActor) {
            Project::query()->orderBy('id')->chunkById(100, function ($projects) use ($fallbackActor) {
                foreach ($projects as $project) {
                    $actor = User::find($project->created_by) ?: $fallbackActor;
                    app(ProjectTaskTemplateService::class)->sync(
                        $project,
                        (string) ($project->origin_track ?: $project->process_track),
                        $actor
                    );

                    if ($project->lifecycle_phase === 'implementation_monitoring') {
                        app(ProjectTaskTemplateService::class)->sync($project, 'implementation_monitoring', $actor);
                    }
                }
            });
        }

        DB::table('tasks')
            ->where('task_scope', 'implementation')
            ->whereNull('archived_at')
            ->update([
                'task_scope' => 'workflow',
                'soi_section' => DB::raw("COALESCE(soi_section, 'implementation_monitoring')"),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Restored task history is intentionally retained on rollback.
    }
};
