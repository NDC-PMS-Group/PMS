<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'mapped_events' => $this->whenLoaded('eventSettings', fn () => $this->eventSettings->pluck('event_key')->values()),
            'draft' => $this->when($this->relationLoaded('draftVersion') && $this->draftVersion, fn () => new NotificationTemplateVersionResource($this->draftVersion)),
            'published' => $this->when($this->relationLoaded('latestPublishedVersion') && $this->latestPublishedVersion, fn () => new NotificationTemplateVersionResource($this->latestPublishedVersion)),
            'versions' => NotificationTemplateVersionResource::collection($this->whenLoaded('versions')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
