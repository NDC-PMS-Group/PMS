<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectFundReleaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'requirement_id' => $this->requirement_id,
            'task_id' => $this->task_id,
            'document_id' => $this->document_id,
            'funding_source_id' => $this->funding_source_id,
            'soi_section' => $this->soi_section,
            'gate_step' => $this->gate_step,
            'release_type' => $this->release_type,
            'status' => $this->status,
            'reference_no' => $this->reference_no,
            'payee' => $this->payee,
            'approved_amount' => $this->approved_amount,
            'amount' => $this->amount,
            'release_date' => $this->release_date?->toDateString(),
            'remarks' => $this->remarks,
            'reviewed_at' => $this->reviewed_at?->toDateTimeString(),
            'released_at' => $this->released_at?->toDateTimeString(),
            'requirement' => new ProjectRequirementResource($this->whenLoaded('requirement')),
            'task' => new TaskResource($this->whenLoaded('task')),
            'document' => new DocumentResource($this->whenLoaded('document')),
            'funding_source' => new FundingSourceResource($this->whenLoaded('fundingSource')),
            'prepared_by' => new UserResource($this->whenLoaded('preparedBy')),
            'reviewed_by' => new UserResource($this->whenLoaded('reviewedBy')),
            'released_by' => new UserResource($this->whenLoaded('releasedBy')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
