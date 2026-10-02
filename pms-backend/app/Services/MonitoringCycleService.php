<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMonitoringCycle;

class MonitoringCycleService
{
    public function aggregateStatus(ProjectMonitoringCycle $cycle): string
    {
        $cycle->loadMissing('reports');
        if ($cycle->reports->isEmpty()) {
            return 'open';
        }

        $statuses = $cycle->reports->pluck('status');

        foreach (['returned', 'submitted', 'open', 'accepted'] as $status) {
            if ($statuses->contains($status)) {
                return $status;
            }
        }

        return 'open';
    }

    public function isComplete(ProjectMonitoringCycle $cycle): bool
    {
        return $this->aggregateStatus($cycle) === 'accepted';
    }

    public function syncProject(ProjectMonitoringCycle $cycle): void
    {
        $cycle->loadMissing('reports');
        $status = $this->aggregateStatus($cycle);
        $latestSubmitted = $cycle->reports->whereNotNull('submitted_at')->sortByDesc('submitted_at')->first();
        $latestReviewed = $cycle->reports->whereNotNull('reviewed_at')->sortByDesc('reviewed_at')->first();

        $cycle->project()->update([
            'monitoring_status' => $cycle->status === 'open' ? 'active' : 'completed',
            'monitoring_submission_status' => $status,
            'monitoring_due_date' => $cycle->due_date,
            'monitoring_instructions' => $cycle->instructions,
            'monitoring_activated_at' => $cycle->opened_at,
            'monitoring_activated_by' => $cycle->opened_by,
            'monitoring_submitted_at' => $latestSubmitted?->submitted_at,
            'monitoring_submitted_by' => $latestSubmitted?->submitted_by,
            'monitoring_reviewed_at' => $latestReviewed?->reviewed_at,
            'monitoring_reviewed_by' => $latestReviewed?->reviewed_by,
            'monitoring_review_notes' => $latestReviewed?->review_notes,
            'monitoring_closed_at' => $cycle->closed_at,
        ]);
    }

    public function activeCycle(Project $project): ?ProjectMonitoringCycle
    {
        return $project->monitoringCycles()->where('status', 'open')->latest('id')->first();
    }

    public function quarterDates(int $year, int $quarter): array
    {
        $start = now()->setDate($year, (($quarter - 1) * 3) + 1, 1)->startOfDay();

        return [$start->toDateString(), $start->copy()->addMonths(3)->subDay()->toDateString()];
    }
}
