<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $priorityProfile = $this->priorityProfile($this->priority);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'task_type' => $this->task_type,
            'soi_section' => $this->soi_section,
            'project_id' => $this->project_id,
            'parent_task_id' => $this->parent_task_id,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'assigned_to' => new UserResource($this->whenLoaded('assignedTo')),
            'assigned_by' => new UserResource($this->whenLoaded('assignedBy')),
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'completion_date' => $this->completion_date?->toDateString(),
            'status' => $this->status,
            'progress_percentage' => $this->progress_percentage,
            'priority' => $this->priority,
            'priority_profile' => $priorityProfile,
            'estimated_hours' => $this->estimated_hours,
            'actual_hours' => $this->actual_hours,
            'parent_task' => new TaskResource($this->whenLoaded('parentTask')),
            'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($history) => [
                'id' => $history->id,
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'from_progress' => $history->from_progress,
                'to_progress' => $history->to_progress,
                'event_type' => $history->event_type,
                'notes' => $history->notes,
                'changed_at' => $history->changed_at?->toDateTimeString(),
                'changed_by' => new UserResource($history->changedBy),
            ])),
            'hierarchy' => [
                'is_parent' => !$this->parent_task_id,
                'is_subtask' => (bool) $this->parent_task_id,
                'level' => $this->parent_task_id ? 1 : 0,
            ],
            'is_milestone' => $this->is_milestone,
            'is_overdue' => $this->is_overdue,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }

    private function priorityProfile(?string $priority): array
    {
        return match ($priority) {
            'critical' => [
                'label' => 'Critical',
                'rank' => 6,
                'severity' => 'blocker',
                'description' => 'Stops delivery or requires immediate executive attention.',
            ],
            'urgent' => [
                'label' => 'Urgent',
                'rank' => 5,
                'severity' => 'same_day',
                'description' => 'Needs same-day action to protect schedule, compliance, or dependency flow.',
            ],
            'high' => [
                'label' => 'High',
                'rank' => 4,
                'severity' => 'priority',
                'description' => 'Important work that should be handled before standard tasks.',
            ],
            'medium', 'normal' => [
                'label' => $priority === 'medium' ? 'Medium' : 'Normal',
                'rank' => 3,
                'severity' => 'standard',
                'description' => 'Planned work with normal delivery priority.',
            ],
            'low' => [
                'label' => 'Low',
                'rank' => 1,
                'severity' => 'backlog',
                'description' => 'Can wait after higher-impact work.',
            ],
            default => [
                'label' => 'Unclassified',
                'rank' => 2,
                'severity' => 'unclassified',
                'description' => 'No priority classification has been set.',
            ],
        };
    }
}
