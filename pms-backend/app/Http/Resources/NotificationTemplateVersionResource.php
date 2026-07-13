<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationTemplateVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'status' => $this->status,
            'subject' => $this->subject,
            'body' => $this->body,
            'variables' => $this->variables ?? [],
            'restored_from_id' => $this->restored_from_id,
            'author' => $this->whenLoaded('author', fn () => $this->author?->full_name),
            'publisher' => $this->whenLoaded('publisher', fn () => $this->publisher?->full_name),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
