<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'email' => $this->email,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'action' => $this->action,
            'action_type' => $this->action_type, // Uses accessor
            'description' => $this->description,
            'model_type' => $this->model_type, // Alias
            'model_id' => $this->model_id, // Alias
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'device_type' => $this->device_type,
            'browser' => $this->browser,
            'browser_version' => $this->browser_version,
            'platform' => $this->platform,
            'platform_version' => $this->platform_version,
            'changes' => $this->changes, // Uses accessor
            'created_at' => $this->created_at,
            'employee' => $this->employee, // Uses accessor
        ];
    }
}