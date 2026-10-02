<?php

namespace App\Http\Resources;

use App\Support\ProjectCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $approval = $this->approvals()->latest('id')->first();
        $thumbnailImage = $this->whenLoaded('images', function () {
            return $this->images->firstWhere('is_thumbnail', true) ?? $this->images->first();
        });
        $galleryThumbnailPath = $thumbnailImage instanceof \App\Models\ProjectImage
            ? $thumbnailImage->file_path
            : null;
        $isSuperAdmin = $request->user()
            && ((int)$request->user()->default_role_id === 1 || $request->user()->hasRole('superadmin'));
        $isExternalProponent = $request->user()
            && ((int) $request->user()->default_role_id === 7 || $request->user()->hasRole('Proponent'));
        $locked = $approval && $approval->overall_status !== 'returned';

        $categoryKey = ProjectCategory::categoryKey(
            $this->origin_track ?: $this->process_track,
            (bool) $this->is_svf
        );

        return [
            'id' => $this->id,
            'record_type' => $this->record_type,
            'investment_status' => $this->investment_status,
            'investment_status_label' => \App\Support\InvestmentLifecycle::LABELS[$this->investment_status ?? ''] ?? null,
            'record_type_label' => match ($this->record_type) { 'project' => 'NDC Project', 'investment' => 'Investment', default => 'Needs classification' },
            'project_type_other' => $this->project_type_other,
            'industry_other' => $this->industry_other,
            'sector_other' => $this->sector_other,
            'operations_start_date' => $this->operations_start_date?->toDateString(),
            'other_financing' => $this->other_financing ?? [],
            'narrative_content' => $this->narrative_content ?? (object) [],
            'additional_locations' => $this->additionalLocations->map(fn ($location) => $location->only(['region_code', 'region_name', 'province_code', 'province_name', 'address'])),
            'project_code' => $this->project_code,
            'title' => $this->title,
            'description' => $this->description,
            'process_track' => $this->process_track,
            'origin_track' => $this->origin_track ?: (in_array($this->process_track, ['bdg_investment', 'spg_traditional', 'spg_ndc_own', 'spg_jv'], true) ? $this->process_track : null),
            'project_category_key' => $categoryKey,
            'project_category_label' => ProjectCategory::label($categoryKey),
            'lifecycle_phase' => $this->lifecycle_phase ?: match ($this->process_track) {
                'implementation_monitoring' => 'implementation_monitoring',
                'divestment' => 'divestment',
                default => 'development',
            },
            'lifecycle_phase_started_at' => $this->lifecycle_phase_started_at?->toDateTimeString(),
            'implementation_started_by' => new UserResource($this->whenLoaded('implementationStartedBy')),
            'date_of_application' => $this->date_of_application?->toDateString(),
            'project_type_id' => $this->project_type_id,
            'industry_id' => $this->industry_id,
            'sector_id' => $this->sector_id,
            'investment_type_id' => $this->investment_type_id,
            'investment_type_other' => $this->investment_type_other,
            'funding_source_id' => $this->funding_source_id,
            'funding_source_other' => $this->funding_source_other,
            'project_type' => new ProjectTypeResource($this->whenLoaded('projectType')),
            'industry' => new IndustryResource($this->whenLoaded('industry')),
            'sector' => new SectorResource($this->whenLoaded('sector')),
            'investment_type' => new InvestmentTypeResource($this->whenLoaded('investmentType')),
            'funding_source' => new FundingSourceResource($this->whenLoaded('fundingSource')),
            'estimated_cost' => $this->estimated_cost,
            'actual_cost' => $this->actual_cost,
            'target_amount_to_raise' => $this->target_amount_to_raise,
            'ndc_participation' => $this->ndc_participation,
            'ndc_investment_criteria' => $this->ndc_investment_criteria ?? [],
            'ndc_investment_criteria_other' => $this->ndc_investment_criteria_other,
            'project_rationale' => $this->project_rationale,
            'company_background' => $this->company_background,
            'target_beneficiaries' => $this->target_beneficiaries,
            'expected_benefits' => $this->expected_benefits,
            'risk_analysis' => $this->risk_analysis,
            'financial_metrics' => $this->financial_metrics ?? [],
            'implementation_milestones' => $this->implementation_milestones ?? [],
            'issues_problems' => $this->issues_problems,
            'next_steps' => $this->next_steps,
            'post_investment_strategy' => $this->post_investment_strategy,
            'monitoring_status' => $this->monitoring_status ?: 'closed',
            'monitoring_submission_status' => $this->monitoring_submission_status ?: 'not_requested',
            'monitoring_draft_saved_at' => $this->monitoring_draft_saved_at?->toDateTimeString(),
            'monitoring_submitted_at' => $this->monitoring_submitted_at?->toDateTimeString(),
            'monitoring_submitted_by' => new UserResource($this->whenLoaded('monitoringSubmittedBy')),
            'monitoring_reviewed_at' => $this->monitoring_reviewed_at?->toDateTimeString(),
            'monitoring_reviewed_by' => new UserResource($this->whenLoaded('monitoringReviewedBy')),
            'monitoring_review_notes' => $this->monitoring_review_notes,
            'monitoring_activated_at' => $this->monitoring_activated_at?->toDateTimeString(),
            'monitoring_activated_by' => new UserResource($this->whenLoaded('monitoringActivatedBy')),
            'monitoring_due_date' => $this->monitoring_due_date?->toDateString(),
            'monitoring_instructions' => $this->monitoring_instructions,
            'monitoring_proponent_access' => (bool) $this->monitoring_proponent_access,
            'monitoring_closed_at' => $this->monitoring_closed_at?->toDateTimeString(),
            'active_monitoring_cycle' => $this->whenLoaded('activeMonitoringCycle', fn () => $this->activeMonitoringCycle ? [
                'id' => $this->activeMonitoringCycle->id,
                'reporting_year' => $this->activeMonitoringCycle->reporting_year,
                'quarter' => $this->activeMonitoringCycle->quarter,
                'period_start' => $this->activeMonitoringCycle->period_start?->toDateString(),
                'period_end' => $this->activeMonitoringCycle->period_end?->toDateString(),
                'due_date' => $this->activeMonitoringCycle->due_date?->toDateString(),
                'instructions' => $this->activeMonitoringCycle->instructions,
                'requested_compliance_types' => $this->activeMonitoringCycle->requested_compliance_types,
                'status' => $this->activeMonitoringCycle->status,
                'reports' => $this->activeMonitoringCycle->relationLoaded('reports')
                    ? $this->activeMonitoringCycle->reports
                        ->sortByDesc(fn ($report) => $report->submitted_at?->getTimestamp() ?: $report->id)
                        ->groupBy('compliance_type')
                        ->map(fn ($items) => $items->first())
                        ->map(
                        fn ($report) => (new ProjectMonitoringReportResource($report))->resolve($request)
                    )->all()
                    : [],
                'reports_list' => $this->activeMonitoringCycle->relationLoaded('reports')
                    ? $this->activeMonitoringCycle->reports
                        ->sortByDesc(fn ($report) => $report->submitted_at?->getTimestamp() ?: $report->id)
                        ->values()
                        ->map(fn ($report) => (new ProjectMonitoringReportResource($report))->resolve($request))
                        ->all()
                    : [],
            ] : null),
            'currency' => $this->currency,
            'current_stage_id' => $this->current_stage_id,
            'status_id' => $this->status_id,
            'current_stage' => new ProjectStageResource($this->whenLoaded('currentStage')),
            'status' => new ProjectStatusResource($this->whenLoaded('status')),
            'proposal_date' => $this->proposal_date?->toDateString(),
            'start_date' => $this->start_date?->toDateString(),
            'target_completion_date' => $this->target_completion_date?->toDateString(),
            'actual_completion_date' => $this->actual_completion_date?->toDateString(),
            'location' => [
                'address' => $this->location_address,
                'region_code' => $this->location_region_code,
                'region_name' => $this->location_region_name,
                'province_code' => $this->location_province_code,
                'province_name' => $this->location_province_name,
                'city_code' => $this->location_city_code,
                'city_name' => $this->location_city_name,
                'barangay_code' => $this->location_barangay_code,
                'barangay_name' => $this->location_barangay_name,
                'street' => $this->location_street,
                'latitude' => $this->location_lat,
                'longitude' => $this->location_lng,
            ],
            'location_address' => $this->location_address,
            'location_region_code' => $this->location_region_code,
            'location_region_name' => $this->location_region_name,
            'location_province_code' => $this->location_province_code,
            'location_province_name' => $this->location_province_name,
            'location_city_code' => $this->location_city_code,
            'location_city_name' => $this->location_city_name,
            'location_barangay_code' => $this->location_barangay_code,
            'location_barangay_name' => $this->location_barangay_name,
            'location_street' => $this->location_street,
            'location_lat' => $this->location_lat,
            'location_lng' => $this->location_lng,
            'thumbnail_url' => $this->thumbnail_url ?: $galleryThumbnailPath,
            'logo_url' => $this->logo_url,
            'project_officer_id' => $this->project_officer_id,
            'workgroup_head_id' => $this->workgroup_head_id,
            'project_officer' => new UserResource($this->whenLoaded('projectOfficer')),
            'workgroup_head' => new UserResource($this->whenLoaded('workgroupHead')),
            'proponent' => [
                'name' => $this->proponent_name,
                'contact' => $this->proponent_contact,
                'email' => $this->proponent_email,
            ],
            'proponent_user' => new UserResource($this->whenLoaded('proponentUser')),
            'proponent_name' => $this->proponent_name,
            'proponent_contact' => $this->proponent_contact,
            'proponent_email' => $this->proponent_email,
            'is_svf' => $this->is_svf,
            'is_legacy' => (bool) $this->is_legacy,
            'legacy_detail' => new ProjectLegacyDetailResource($this->whenLoaded('legacyDetail')),
            'legacy_review' => $this->when((bool) $this->is_legacy, function () {
                $missing = collect([
                    'project type' => $this->project_type_id,
                    'industry' => $this->industry_id,
                    'sector' => $this->sector_id,
                    'project officer' => $this->project_officer_id,
                    'proponent contact' => $this->proponent_email,
                ])->filter(fn ($value) => blank($value))->keys()->values()->all();
                $isImplementation = strtolower((string) ($this->currentStage?->name ?? '')) === 'implementation & monitoring';
                $hasLinkedProponent = $this->relationLoaded('proponentUser')
                    ? (bool) $this->proponentUser
                    : false;

                return [
                    'missing_fields' => $missing,
                    'monitoring_ready' => $isImplementation && !$this->is_archived && $hasLinkedProponent,
                    'monitoring_message' => $isImplementation
                        ? ($hasLinkedProponent ? 'Ready for NDC to open a monitoring cycle.' : 'Link an active external Proponent account before opening monitoring.')
                        : 'Move the project to Implementation & Monitoring before opening monitoring.',
                ];
            }),
            'is_archived' => $this->is_archived,
            'is_overdue' => $this->is_overdue,
            'progress_percentage' => $this->progress_percentage,
            'approval_lock' => [
                'is_locked' => (bool) ($locked && !$isSuperAdmin),
                'can_override' => (bool) $isSuperAdmin,
                'approval_status' => $approval?->overall_status,
                'message' => $locked
                    ? 'Project details are locked after submission or approval. Request a revision before editing.'
                    : null,
            ],
            'approval_timing' => $approval ? [
                'current_step_started_at' => $approval->current_step_started_at?->toDateTimeString(),
                'sla_due_at' => $approval->sla_due_at?->toDateTimeString(),
                'is_overdue' => (bool) ($approval->sla_due_at && now()->isAfter($approval->sla_due_at) && !$approval->completed_at),
            ] : null,
            'members' => ProjectMemberResource::collection($this->whenLoaded('members')),
            'invitations' => $this->whenLoaded('invitations', function() {
                return $this->invitations->map(fn($invite) => [
                    'id' => $invite->id,
                    'project_id' => $invite->project_id,
                    'email' => $invite->email,
                    'role_id' => $invite->role_id,
                    'role' => $invite->role ? [
                        'id' => $invite->role->id,
                        'name' => $invite->role->name
                    ] : null,
                    'assignment_type' => $invite->assignment_type,
                    'can_view' => $invite->can_view,
                    'can_edit' => $invite->can_edit,
                    'can_delete' => $invite->can_delete,
                    'can_approve' => $invite->can_approve,
                    'can_manage_members' => $invite->can_manage_members,
                    'status' => $invite->status,
                    'invited_by_id' => $invite->invited_by_id,
                    'invited_by' => $invite->invitedBy ? [
                        'id' => $invite->invitedBy->id,
                        'full_name' => $invite->invitedBy->full_name
                    ] : null,
                    'created_at' => $invite->created_at?->toDateTimeString(),
                    'updated_at' => $invite->updated_at?->toDateTimeString(),
                ]);
            }),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents', function () use ($isExternalProponent) {
                if (!$isExternalProponent) {
                    return $this->documents;
                }

                $internalDocumentIds = $this->relationLoaded('requirements')
                    ? $this->requirements
                        ->where('visibility', 'internal_only')
                        ->pluck('document_id')
                        ->filter()
                        ->all()
                    : [];

                return $this->documents
                    ->reject(fn ($document) => in_array((int) $document->id, array_map('intval', $internalDocumentIds), true))
                    ->values();
            })),
            'images' => ProjectImageResource::collection($this->whenLoaded('images')),
            'requirements' => ProjectRequirementResource::collection($this->whenLoaded('requirements', function () use ($isExternalProponent) {
                return $isExternalProponent
                    ? $this->requirements
                        ->where('visibility', 'proponent_visible')
                        ->values()
                    : $this->requirements;
            })),
            'fund_releases' => ProjectFundReleaseResource::collection($this->whenLoaded('fundReleases')),
            'fund_release_summary' => $this->whenLoaded('fundReleases', fn () => $this->fundReleaseSummary()),
            'tasks_count' => $this->when(isset($this->tasks_count), $this->tasks_count),
            'documents_count' => $this->when(isset($this->documents_count), $this->documents_count),
            'created_by_id' => $this->created_by,
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }

    private function fundReleaseSummary(): array
    {
        $target = (float) ($this->ndc_participation ?: $this->target_amount_to_raise ?: $this->estimated_cost ?: 0);
        $released = (float) $this->fundReleases
            ->where('status', 'released')
            ->sum(fn ($release) => (float) $release->amount);

        return [
            'target_amount' => round($target, 2),
            'released_amount' => round($released, 2),
            'remaining_amount' => round(max($target - $released, 0), 2),
            'release_count' => $this->fundReleases->count(),
            'released_count' => $this->fundReleases->where('status', 'released')->count(),
            'progress' => $target > 0 ? min(100, round(($released / $target) * 100)) : 0,
        ];
    }
}
