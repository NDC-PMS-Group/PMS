<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $spgTracks = ['spg_traditional', 'spg_ndc_own', 'spg_jv'];

        $projects = DB::table('projects')
            ->whereIn('process_track', $spgTracks)
            ->where('project_code', 'like', 'BDG-%')
            ->orderBy('id')
            ->get(['id', 'project_code']);

        foreach ($projects as $project) {
            if (!preg_match('/^BDG-(\d{4})-(\d+)$/', (string) $project->project_code, $matches)) {
                continue;
            }

            $year = $matches[1];
            $nextCode = $this->nextAvailableCode('SPG', $year);

            DB::table('projects')
                ->where('id', $project->id)
                ->update([
                    'project_code' => $nextCode,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Irreversible data correction: do not restore known-wrong BDG prefixes for SPG projects.
    }

    private function nextAvailableCode(string $prefix, string $year): string
    {
        $maxNumber = DB::table('projects')
            ->where('project_code', 'like', "{$prefix}-{$year}-%")
            ->pluck('project_code')
            ->map(function ($code) use ($prefix, $year) {
                $pattern = '/^' . preg_quote($prefix, '/') . '-' . preg_quote($year, '/') . '-(\d+)$/';
                return preg_match($pattern, (string) $code, $matches) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        return sprintf('%s-%s-%03d', $prefix, $year, $maxNumber + 1);
    }
};
