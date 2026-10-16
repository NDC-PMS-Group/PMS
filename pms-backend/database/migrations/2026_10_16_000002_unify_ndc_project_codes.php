<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('project_code_renames')) {
            Schema::create('project_code_renames', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->primary();
                $table->string('old_code');
                $table->string('new_code')->unique();
            });
        }

        DB::transaction(function () {
            // Query builder includes archived and soft-deleted projects. Preserve historical text/files.
            $projects = DB::table('projects')->orderBy('id')->lockForUpdate()->get(['id', 'project_code', 'created_at']);
            $used = [];
            $max = [];
            foreach ($projects as $project) {
                if (preg_match('/^NDC-(\d{4})-(\d+)$/', $project->project_code, $m)) {
                    $used[$m[1]][(int) $m[2]] = true;
                    $max[$m[1]] = max($max[$m[1]] ?? 0, (int) $m[2]);
                }
            }
            foreach ($projects as $project) {
                if (preg_match('/^NDC-\d{4}-\d+$/', $project->project_code)) {
                    continue;
                }
                $matched = preg_match('/(?:^|-)(\d{4})-(\d+)$/', $project->project_code, $m);
                $year = $matched ? $m[1] : (preg_match('/(?:^|-)(\d{4})(?:-|$)/', $project->project_code, $y) ? $y[1] : substr((string) ($project->created_at ?? now()), 0, 4));
                $number = $matched ? (int) $m[2] : 0;
                if ($number < 1 || isset($used[$year][$number])) {
                    $number = ($max[$year] ?? 0) + 1;
                }
                $used[$year][$number] = true;
                $max[$year] = max($max[$year] ?? 0, $number);
                $code = sprintf('NDC-%s-%03d', $year, $number);
                DB::table('project_code_renames')->insert(['project_id' => $project->id, 'old_code' => $project->project_code, 'new_code' => $code]);
                DB::table('projects')->where('id', $project->id)->update(['project_code' => $code]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $renames = DB::table('project_code_renames')->orderBy('project_id')->get();
            foreach ($renames as $rename) {
                $current = DB::table('projects')->where('id', $rename->project_id)->value('project_code');
                if ($current !== null && $current !== $rename->new_code) {
                    throw new RuntimeException('A project code changed after migration; restore codes manually from project_code_renames.');
                }
                if (DB::table('projects')->where('project_code', $rename->old_code)->where('id', '!=', $rename->project_id)->exists()) {
                    throw new RuntimeException('An original project code is now in use; resolve the collision before rollback.');
                }
            }
            foreach ($renames as $rename) {
                DB::table('projects')->where('id', $rename->project_id)->update(['project_code' => $rename->old_code]);
            }
        });
        Schema::drop('project_code_renames');
    }
};
