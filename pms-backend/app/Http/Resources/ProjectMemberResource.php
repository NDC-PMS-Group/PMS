<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'user_id' => $this->user_id,
            'role_id' => $this->role_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'role' => new RoleResource($this->whenLoaded('role')),
            'assignment_type' => $this->assignment_type,
            'can_view' => (bool) $this->can_view,
            'can_edit' => (bool) $this->can_edit,
            'can_delete' => (bool) $this->can_delete,
            'can_approve' => (bool) $this->can_approve,
            'can_manage_members' => (bool) $this->can_manage_members,
            'permissions' => [
                'can_view' => (bool) $this->can_view,
                'can_edit' => (bool) $this->can_edit,
                'can_delete' => (bool) $this->can_delete,
                'can_approve' => (bool) $this->can_approve,
                'can_manage_members' => (bool) $this->can_manage_members,
            ],
            'assigned_by' => new UserResource($this->whenLoaded('assignedBy')),
            'assigned_at' => $this->assigned_at?->toDateTimeString(),
            'removed_at' => $this->removed_at?->toDateTimeString(),
        ];
    }
}
