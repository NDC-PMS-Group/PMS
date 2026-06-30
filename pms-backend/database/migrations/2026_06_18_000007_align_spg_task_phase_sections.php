<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->retargetTaskSections('spg_jv', [
            '1.%' => 'intake',
            '2.%' => 'due_diligence',
            '3.%' => 'board_approval',
            '4.%' => 'board_approval',
            '5.%' => 'agreement_fund_release',
        ]);

        $this->retargetTaskSections('spg_ndc_own', [
            '1.%' => 'intake',
            '2.%' => 'due_diligence',
            '3.%' => 'management_review',
            '4.%' => 'board_approval',
            '5. DED%' => 'agreement_fund_release',
            '5. Construction implementation%' => 'implementation_monitoring',
            '6.%' => 'implementation_monitoring',
        ]);

        $legacyConstructionTaskIds = DB::table('tasks')
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->where('projects.process_track', 'spg_ndc_own')
            ->where('tasks.title', 'like', '5. Construction implementation%')
            ->pluck('tasks.id');

        foreach ($legacyConstructionTaskIds as $taskId) {
            $title = DB::table('tasks')->where('id', $taskId)->value('title');
            DB::table('tasks')
                ->where('id', $taskId)
                ->update([
                    'title' => str_replace('5. Construction implementation', '6. Construction implementation', (string) $title),
                    'soi_section' => 'implementation_monitoring',
                ]);
        }
    }

    private function retargetTaskSections(string $track, array $prefixMap): void
    {
        foreach ($prefixMap as $titlePattern => $soiSection) {
            $parentIds = DB::table('tasks')
                ->join('projects', 'projects.id', '=', 'tasks.project_id')
                ->where('projects.process_track', $track)
                ->whereNull('tasks.parent_task_id')
                ->where('tasks.title', 'like', $titlePattern)
                ->pluck('tasks.id');

            if ($parentIds->isEmpty()) {
                continue;
            }

            DB::table('tasks')
                ->whereIn('id', $parentIds->all())
                ->update(['soi_section' => $soiSection]);

            DB::table('tasks')
                ->whereIn('parent_task_id', $parentIds->all())
                ->update(['soi_section' => $soiSection]);
        }
    }

    public function down(): void
    {
        // Data correction only. Restoring heuristic sections would make SPG
        // phases appear out of source-sheet order again.
    }
};
