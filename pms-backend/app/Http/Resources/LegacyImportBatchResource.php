<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegacyImportBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'imported_by' => new UserResource($this->whenLoaded('importedBy')),
            'total_rows' => $this->total_rows,
            'created_count' => $this->created_count,
            'skipped_count' => $this->skipped_count,
            'status_summary' => $this->status_summary ?? [],
            'warnings' => $this->warnings ?? [],
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
