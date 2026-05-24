<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $approval = $this->approvals()->latest('id')->first();
        $isSuperAdmin = $request->user()
            && ((int)$request->user()->default_role_id === 1 || $request->user()->hasRole('superadmin'));
        $locked = $approval && !in_array($approval->overall_status, ['pending', 'returned'], true);

        return [
            'id' => $this->id,
            'project_code' => $this->project_code,
            'title' => $this->title,
            'description' => $this->description,
            'process_track' => $this->process_track,
            'date_of_application' => $this->date_of_application?->toDateString(),
            'project_type_id' => $this->project_type_id,
            'industry_id' => $this->industry_id,
            'sector_id' => $this->sector_id,
            'investment_type_id' => $this->investment_type_id,
            'funding_source_id' => $this->funding_source_id,
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
            'thumbnail_url' => $this->thumbnail_url,
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
            'proponent_name' => $this->proponent_name,
            'proponent_contact' => $this->proponent_contact,
            'proponent_email' => $this->proponent_email,
            'is_svf' => $this->is_svf,
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
            'members' => ProjectMemberResource::collection($this->whenLoaded('members')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'requirements' => ProjectRequirementResource::collection($this->whenLoaded('requirements')),
            'tasks_count' => $this->when(isset($this->tasks_count), $this->tasks_count),
            'documents_count' => $this->when(isset($this->documents_count), $this->documents_count),
            'created_by_id' => $this->created_by,
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
