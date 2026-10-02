<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COMPLIANCE_TYPES = ['employment', 'financial', 'progress'];

    public function up(): void
    {
        Schema::create('project_monitoring_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('reporting_year');
            $table->unsignedTinyInteger('quarter');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date')->nullable();
            $table->text('instructions')->nullable();
            $table->json('requested_compliance_types');
            $table->string('status', 30)->default('open');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'reporting_year', 'quarter'], 'monitoring_cycle_project_period_unique');
            $table->index(['status', 'due_date'], 'monitoring_cycle_status_due_index');
            $table->index(['reporting_year', 'quarter'], 'monitoring_cycle_period_index');
        });

        Schema::table('project_monitoring_reports', function (Blueprint $table) {
            $table->foreignId('monitoring_cycle_id')
                ->nullable()
                ->after('project_id')
                ->constrained('project_monitoring_cycles')
                ->nullOnDelete();
            $table->index(['monitoring_cycle_id', 'compliance_type'], 'monitoring_report_cycle_type_index');
        });

        $this->backfillCycles();

        DB::table('projects')
            ->where('monitoring_status', 'active')
            ->where('monitoring_submission_status', 'draft')
            ->update(['monitoring_submission_status' => 'open']);
    }

    private function backfillCycles(): void
    {
        $projects = DB::table('projects')
            ->whereIn('id', function ($query) {
                $query->select('project_id')->from('project_monitoring_reports');
            })
            ->orWhere('monitoring_status', 'active')
            ->orderBy('id')
            ->get();

        foreach ($projects as $project) {
            $groups = DB::table('project_monitoring_reports')
                ->where('project_id', $project->id)
                ->orderBy('reporting_year')
                ->orderBy('quarter')
                ->get()
                ->groupBy(fn ($report) => "{$report->reporting_year}-{$report->quarter}");

            if ($groups->isEmpty()) {
                $reference = new DateTimeImmutable($project->monitoring_activated_at ?: $project->updated_at ?: 'now');
                $quarter = (int) ceil(((int) $reference->format('n')) / 3);
                $groups = collect(["{$reference->format('Y')}-{$quarter}" => collect()]);
            }

            $keys = $groups->keys()->values();
            $latestKey = $keys->last();

            foreach ($groups as $key => $reports) {
                [$year, $quarter] = array_map('intval', explode('-', $key));
                $first = $reports->first();
                $periodStart = $first?->period_start ?: sprintf('%d-%02d-01', $year, (($quarter - 1) * 3) + 1);
                $periodEnd = $first?->period_end ?: (new DateTimeImmutable($periodStart))->modify('+3 months -1 day')->format('Y-m-d');
                $requestedTypes = $reports->pluck('compliance_type')->filter()->unique()->values()->all();
                $isOpen = $project->monitoring_status === 'active' && $key === $latestKey;

                $cycleId = DB::table('project_monitoring_cycles')->insertGetId([
                    'project_id' => $project->id,
                    'reporting_year' => $year,
                    'quarter' => $quarter,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'due_date' => $first?->due_date ?: $project->monitoring_due_date,
                    'instructions' => $project->monitoring_instructions,
                    'requested_compliance_types' => json_encode($requestedTypes ?: self::COMPLIANCE_TYPES),
                    'status' => $isOpen ? 'open' : 'closed',
                    'opened_by' => $project->monitoring_activated_by ?: $project->created_by,
                    'opened_at' => $project->monitoring_activated_at ?: $first?->created_at ?: $project->created_at,
                    'closed_at' => $isOpen ? null : ($project->monitoring_closed_at ?: $first?->reviewed_at ?: $first?->updated_at),
                    'created_at' => $first?->created_at ?: $project->created_at ?: now(),
                    'updated_at' => $first?->updated_at ?: $project->updated_at ?: now(),
                ]);

                if ($reports->isNotEmpty()) {
                    DB::table('project_monitoring_reports')
                        ->whereIn('id', $reports->pluck('id'))
                        ->update(['monitoring_cycle_id' => $cycleId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('project_monitoring_reports', function (Blueprint $table) {
            $table->dropIndex('monitoring_report_cycle_type_index');
            $table->dropConstrainedForeignId('monitoring_cycle_id');
        });

        Schema::dropIfExists('project_monitoring_cycles');
    }
};
