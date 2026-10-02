<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectAgreementFormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project_approval_id' => $this->project_approval_id,
            'approval_step_id' => $this->approval_step_id,
            'document_id' => $this->document_id,
            'agreement_type' => $this->agreement_type,
            'parties' => $this->parties ?? [],
            'term_sheet' => $this->term_sheet,
            'status' => $this->status,
            'return_reason' => $this->return_reason,
            'document' => new DocumentResource($this->whenLoaded('document')),
            'prepared_by' => new UserResource($this->whenLoaded('preparedBy')),
            'submitted_by' => new UserResource($this->whenLoaded('submittedBy')),
            'returned_by' => new UserResource($this->whenLoaded('returnedBy')),
            'submitted_at' => $this->submitted_at?->toDateTimeString(),
            'returned_at' => $this->returned_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
