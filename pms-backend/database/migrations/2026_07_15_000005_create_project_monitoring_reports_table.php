<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_monitoring_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('reporting_year');
            $table->unsignedTinyInteger('quarter');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('jobs_generated')->default(0);
            $table->unsignedInteger('jobs_retained')->default(0);
            $table->decimal('revenue', 18, 2)->default(0);
            $table->decimal('remittance', 18, 2)->default(0);
            $table->text('milestones')->nullable();
            $table->text('impact')->nullable();
            $table->text('monitoring_narrative')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'reporting_year', 'quarter'], 'monitoring_project_period_unique');
            $table->index(['status', 'due_date'], 'monitoring_status_due_index');
            $table->index(['reporting_year', 'quarter'], 'monitoring_period_index');
        });

        $this->backfillCurrentMonitoringSnapshots();
    }

    private function backfillCurrentMonitoringSnapshots(): void
    {
        DB::table('projects')
            ->where(function ($query) {
                $query->where('monitoring_status', '!=', 'closed')
                    ->orWhereNotNull('monitoring_submitted_at')
                    ->orWhereNotNull('financial_metrics');
            })
            ->orderBy('id')
            ->get()
            ->each(function ($project) {
                $metrics = json_decode($project->financial_metrics ?: '{}', true) ?: [];
                if ($project->monitoring_status === 'closed' && empty($metrics)) {
                    return;
                }

                $reference = $project->monitoring_submitted_at
                    ?: $project->monitoring_activated_at
                    ?: $project->updated_at
                    ?: now()->toDateTimeString();
                $date = new DateTimeImmutable($reference);
                $quarter = (int) ceil(((int) $date->format('n')) / 3);
                $startMonth = (($quarter - 1) * 3) + 1;
                $periodStart = new DateTimeImmutable(sprintf('%d-%02d-01', (int) $date->format('Y'), $startMonth));
                $periodEnd = $periodStart->modify('+3 months -1 day');
                $status = $project->monitoring_submission_status ?: 'draft';
                if ($status === 'approved') {
                    $status = 'accepted';
                }
                if ($status === 'not_requested') {
                    $status = 'draft';
                }

                DB::table('project_monitoring_reports')->insertOrIgnore([
                    'project_id' => $project->id,
                    'reporting_year' => (int) $date->format('Y'),
                    'quarter' => $quarter,
                    'period_start' => $periodStart->format('Y-m-d'),
                    'period_end' => $periodEnd->format('Y-m-d'),
                    'due_date' => $project->monitoring_due_date,
                    'status' => $status,
                    'jobs_generated' => (int) ($metrics['jobs_generated'] ?? ((int) ($metrics['jobs_generated_direct'] ?? 0) + (int) ($metrics['jobs_generated_indirect'] ?? 0))),
                    'jobs_retained' => (int) ($metrics['jobs_retained'] ?? $metrics['retained_jobs'] ?? 0),
                    'revenue' => (float) ($metrics['revenue'] ?? $metrics['actual_revenue'] ?? 0),
                    'remittance' => (float) ($metrics['remittance'] ?? $metrics['dividend_remittance'] ?? 0),
                    'milestones' => $metrics['milestones'] ?? $metrics['monitoring_indicators'] ?? null,
                    'impact' => $metrics['impact'] ?? $metrics['social_impact_notes'] ?? null,
                    'monitoring_narrative' => $metrics['monitoring_narrative'] ?? null,
                    'review_notes' => $project->monitoring_review_notes,
                    'created_by' => $project->monitoring_activated_by ?? $project->created_by,
                    'submitted_by' => $project->monitoring_submitted_by,
                    'submitted_at' => $project->monitoring_submitted_at,
                    'reviewed_by' => $project->monitoring_reviewed_by,
                    'reviewed_at' => $project->monitoring_reviewed_at,
                    'created_at' => $project->monitoring_activated_at ?? $project->created_at ?? now(),
                    'updated_at' => $project->updated_at ?? now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_monitoring_reports');
    }
};
