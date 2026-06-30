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
            'download_url' => url("/api/documents/{$this->id}/download"),
            'file_size' => $this->file_size,
            'file_type' => $this->file_type,
            'category' => $this->category,
            'version' => $this->version,
            'is_public' => $this->is_public,
            'requires_approval' => $this->requires_approval,
            'submission_status' => $this->submission_status ?? 'draft',
            'uploaded_by' => new UserResource($this->whenLoaded('uploadedBy')),
            'uploaded_at' => $this->uploaded_at?->toDateTimeString(),
            'submitted_by' => new UserResource($this->whenLoaded('submittedBy')),
            'submitted_at' => $this->submitted_at?->toDateTimeString(),
            'update_requested_by' => new UserResource($this->whenLoaded('updateRequestedBy')),
            'update_requested_at' => $this->update_requested_at?->toDateTimeString(),
            'update_request_reason' => $this->update_request_reason,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'task' => new TaskResource($this->whenLoaded('task')),
        ];
    }
}
