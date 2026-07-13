<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_key' => $this->event_key,
            'channel' => $this->channel,
            'recipient' => $this->mask((string) $this->recipient_address),
            'subject' => $this->subject,
            'status' => $this->status,
            'is_test' => $this->is_test,
            'attempts' => $this->attempts,
            'failure_reason' => $this->failure_reason,
            'template_version' => $this->templateVersion?->version,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function mask(string $address): string
    {
        if (! str_contains($address, '@')) {
            return '***';
        }

        [$local, $domain] = explode('@', $address, 2);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('*', max(3, mb_strlen($local) - mb_strlen($visible))).'@'.$domain;
    }
}
