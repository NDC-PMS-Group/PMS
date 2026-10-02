<?php

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectTaskTemplateService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $fallbackActor = User::query()->orderBy('id')->first();

        if (!$fallbackActor) {
            return;
        }

        Project::query()
            ->where('is_deleted', false)
            ->orderBy('id')
            ->chunkById(100, function ($projects) use ($fallbackActor) {
                foreach ($projects as $project) {
                    $actor = User::find($project->created_by) ?: $fallbackActor;
                    $track = (string) ($project->origin_track ?: $project->process_track ?: 'bdg_investment');

                    app(ProjectTaskTemplateService::class)->sync($project, $track, $actor);

                    if ($project->lifecycle_phase === 'implementation_monitoring') {
                        app(ProjectTaskTemplateService::class)->sync($project, 'implementation_monitoring', $actor);
                    }
                }
            });
    }

    public function down(): void
    {
        // Backfilled workflow tasks are retained to preserve project history.
    }
};
