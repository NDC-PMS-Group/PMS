<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'task_type' => $this->task_type,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'assigned_to' => new UserResource($this->whenLoaded('assignedTo')),
            'assigned_by' => new UserResource($this->whenLoaded('assignedBy')),
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'completion_date' => $this->completion_date?->toDateString(),
            'status' => $this->status,
            'progress_percentage' => $this->progress_percentage,
            'priority' => $this->priority,
            'estimated_hours' => $this->estimated_hours,
            'actual_hours' => $this->actual_hours,
            'parent_task' => new TaskResource($this->whenLoaded('parentTask')),
            'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
            'is_milestone' => $this->is_milestone,
            'is_overdue' => $this->is_overdue,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}