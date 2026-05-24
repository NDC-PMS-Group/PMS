<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectRequirementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'document_id' => $this->document_id,
            'group_name' => $this->group_name,
            'item_name' => $this->item_name,
            'source_document' => $this->source_document,
            'track' => $this->track,
            'is_required' => $this->is_required,
            'is_applicable' => $this->is_applicable,
            'svf_only' => $this->svf_only,
            'status' => $this->status,
            'due_date' => $this->due_date?->toDateString(),
            'received_at' => $this->received_at?->toDateTimeString(),
            'remarks' => $this->remarks,
            'sort_order' => $this->sort_order,
            'document' => new DocumentResource($this->whenLoaded('document')),
            'received_by' => new UserResource($this->whenLoaded('receivedBy')),
        ];
    }
}
