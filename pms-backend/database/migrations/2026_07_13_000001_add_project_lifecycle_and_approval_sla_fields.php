<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('origin_track', 80)->nullable()->after('process_track')->index();
            $table->string('lifecycle_phase', 50)->default('development')->after('origin_track')->index();
            $table->timestamp('lifecycle_phase_started_at')->nullable()->after('lifecycle_phase');
        });

        Schema::table('approval_steps', function (Blueprint $table) {
            $table->unsignedSmallInteger('sla_days')->nullable()->after('soi_section');
        });

        Schema::table('project_approvals', function (Blueprint $table) {
            $table->timestamp('current_step_started_at')->nullable()->after('current_step_id');
            $table->timestamp('sla_due_at')->nullable()->after('current_step_started_at')->index();
        });

        DB::table('projects')->orderBy('id')->chunkById(200, function ($projects) {
            foreach ($projects as $project) {
                $originTrack = in_array($project->process_track, [
                    'bdg_investment',
                    'spg_traditional',
                    'spg_ndc_own',
                    'spg_jv',
                ], true) ? $project->process_track : null;

                $phase = match ($project->process_track) {
                    'implementation_monitoring' => 'implementation_monitoring',
                    'divestment' => 'divestment',
                    default => 'development',
                };

                DB::table('projects')->where('id', $project->id)->update([
                    'origin_track' => $originTrack,
                    'lifecycle_phase' => $phase,
                    'lifecycle_phase_started_at' => $project->updated_at ?? $project->created_at,
                ]);
            }
        });

        DB::table('project_approvals')->whereNull('current_step_started_at')->update([
            'current_step_started_at' => DB::raw('started_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('project_approvals', function (Blueprint $table) {
            $table->dropIndex(['sla_due_at']);
            $table->dropColumn(['current_step_started_at', 'sla_due_at']);
        });

        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropColumn('sla_days');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['origin_track']);
            $table->dropIndex(['lifecycle_phase']);
            $table->dropColumn(['origin_track', 'lifecycle_phase', 'lifecycle_phase_started_at']);
        });
    }
};
