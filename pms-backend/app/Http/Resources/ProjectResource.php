<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_code' => $this->project_code,
            'title' => $this->title,
            'description' => $this->description,
            'project_type' => new ProjectTypeResource($this->whenLoaded('projectType')),
            'industry' => new IndustryResource($this->whenLoaded('industry')),
            'sector' => new SectorResource($this->whenLoaded('sector')),
            'investment_type' => new InvestmentTypeResource($this->whenLoaded('investmentType')),
            'funding_source' => new FundingSourceResource($this->whenLoaded('fundingSource')),
            'estimated_cost' => $this->estimated_cost,
            'actual_cost' => $this->actual_cost,
            'currency' => $this->currency,
            'current_stage' => new ProjectStageResource($this->whenLoaded('currentStage')),
            'status' => new ProjectStatusResource($this->whenLoaded('status')),
            'proposal_date' => $this->proposal_date?->toDateString(),
            'start_date' => $this->start_date?->toDateString(),
            'target_completion_date' => $this->target_completion_date?->toDateString(),
            'actual_completion_date' => $this->actual_completion_date?->toDateString(),
            'location' => [
                'address' => $this->location_address,
                'latitude' => $this->location_lat,
                'longitude' => $this->location_lng,
            ],
            // Structured address (1:1 child row). Null when not yet captured —
            // older projects predate this table and stay legacy-only.
            'address' => $this->whenLoaded('address', fn () => $this->address ? [
                'id'                 => $this->address->id,
                'house_number'       => $this->address->house_number,
                'floor'              => $this->address->floor,
                'street'             => $this->address->street,
                'barangay'           => $this->address->barangay,
                'city_municipality'  => $this->address->city_municipality,
                'province'           => $this->address->province,
                'region'             => $this->address->region,
                'country'            => $this->address->country,
                'zip_code'           => $this->address->zip_code,
                'latitude'           => $this->address->latitude !== null ? (float) $this->address->latitude : null,
                'longitude'          => $this->address->longitude !== null ? (float) $this->address->longitude : null,
            ] : null),
            'thumbnail_url' => $this->thumbnail_url,
            'logo_url' => $this->logo_url,
            'project_officer' => new UserResource($this->whenLoaded('projectOfficer')),
            'workgroup_head' => new UserResource($this->whenLoaded('workgroupHead')),
            'proponent' => [
                'name' => $this->proponent_name,
                'contact' => $this->proponent_contact,
                'email' => $this->proponent_email,
            ],
            'is_svf' => $this->is_svf,
            'is_archived' => $this->is_archived,
            'is_overdue' => $this->is_overdue,
            'progress_percentage' => $this->progress_percentage,
            'members' => ProjectMemberResource::collection($this->whenLoaded('members')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'tasks_count' => $this->when(isset($this->tasks_count), $this->tasks_count),
            'documents_count' => $this->when(isset($this->documents_count), $this->documents_count),
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}