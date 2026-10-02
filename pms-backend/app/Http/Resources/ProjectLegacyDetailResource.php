<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectLegacyDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'legacy_import_batch_id' => $this->legacy_import_batch_id,
            'batch' => new LegacyImportBatchResource($this->whenLoaded('batch')),
            'source_row' => $this->source_row,
            'source_file' => $this->source_file,
            'source_status_raw' => $this->source_status_raw,
            'source_cost_raw' => $this->source_cost_raw,
            'source_fund_released_raw' => $this->source_fund_released_raw,
            'source_partner_raw' => $this->source_partner_raw,
            'source_remarks' => $this->source_remarks,
            'parse_warnings' => $this->parse_warnings ?? [],
            'detail_status' => $this->detail_status,
            'completed_at' => $this->completed_at?->toDateTimeString(),
            'completed_by' => new UserResource($this->whenLoaded('completedBy')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
