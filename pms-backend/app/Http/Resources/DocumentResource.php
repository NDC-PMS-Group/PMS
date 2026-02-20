<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'file_size' => $this->file_size,
            'file_type' => $this->file_type,
            'category' => $this->category,
            'version' => $this->version,
            'is_public' => $this->is_public,
            'requires_approval' => $this->requires_approval,
            'uploaded_by' => new UserResource($this->whenLoaded('uploadedBy')),
            'uploaded_at' => $this->uploaded_at->toDateTimeString(),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'task' => new TaskResource($this->whenLoaded('task')),
        ];
    }
}