<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectMonitoringReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'monitoring_cycle_id' => $this->monitoring_cycle_id,
            'reporting_year' => $this->reporting_year,
            'quarter' => $this->quarter,
            'compliance_type' => $this->compliance_type,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'employment_period_start' => $this->employment_period_start?->toDateString(),
            'employment_period_end' => $this->employment_period_end?->toDateString(),
            'financial_period_start' => $this->financial_period_start?->toDateString(),
            'financial_period_end' => $this->financial_period_end?->toDateString(),
            'narrative_period_start' => $this->narrative_period_start?->toDateString(),
            'narrative_period_end' => $this->narrative_period_end?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
            'jobs_generated' => $this->jobs_generated,
            'jobs_generated_male' => $this->jobs_generated_male,
            'jobs_generated_female' => $this->jobs_generated_female,
            'jobs_retained' => $this->jobs_retained,
            'jobs_retained_male' => $this->jobs_retained_male,
            'jobs_retained_female' => $this->jobs_retained_female,
            'revenue' => $this->revenue !== null ? (float) $this->revenue : null,
            'remittance' => $this->remittance !== null ? (float) $this->remittance : null,
            'milestones' => $this->milestones,
            'impact' => $this->impact,
            'monitoring_narrative' => $this->monitoring_narrative,
            'review_notes' => $this->review_notes,
            'submitted_at' => $this->submitted_at?->toDateTimeString(),
            'reviewed_at' => $this->reviewed_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'project_code' => $this->project->project_code,
                'record_type' => $this->project->record_type,
                'record_type_label' => match ($this->project->record_type) {
                    'project' => 'NDC Project',
                    'investment' => 'Investment',
                    default => 'Needs classification',
                },
                'investment_status_label' => \App\Support\InvestmentLifecycle::LABELS[$this->project->investment_status ?? ''] ?? null,
                'title' => $this->project->title,
                'proponent_name' => $this->project->proponent_name,
                'proponent_email' => $this->project->proponent_email,
                'monitoring_instructions' => $this->project->monitoring_instructions,
                'project_officer' => $this->project->projectOfficer ? [
                    'id' => $this->project->projectOfficer->id,
                    'full_name' => $this->project->projectOfficer->full_name,
                ] : null,
            ]),
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'submitted_by' => new UserResource($this->whenLoaded('submittedBy')),
            'reviewed_by' => new UserResource($this->whenLoaded('reviewedBy')),
        ];
    }
}
