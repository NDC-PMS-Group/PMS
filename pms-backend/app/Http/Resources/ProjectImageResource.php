<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProjectImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'url' => $this->file_path ? url(Storage::url($this->file_path)) : null,
            'file_size' => $this->file_size,
            'file_type' => $this->file_type,
            'is_thumbnail' => (bool) $this->is_thumbnail,
            'sort_order' => $this->sort_order,
            'uploaded_by' => new UserResource($this->whenLoaded('uploadedBy')),
            'uploaded_at' => $this->uploaded_at?->toDateTimeString(),
        ];
    }
}
