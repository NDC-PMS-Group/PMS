<?php

namespace App\Services;

use App\Jobs\SendNotificationEmailJob;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplateVersion;
use Illuminate\Support\Facades\Log;

class NotificationDeliveryService
{
    public function queueEmail(
        string $recipientEmail,
        string $subject,
        string $html,
        ?int $notificationId = null,
        ?int $userId = null,
        ?string $eventKey = null,
        ?NotificationTemplateVersion $templateVersion = null,
        array $context = [],
        bool $isTest = false
    ): NotificationDelivery {
        $delivery = NotificationDelivery::create([
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'template_version_id' => $templateVersion?->id,
            'event_key' => $eventKey,
            'channel' => 'email',
            'recipient_address' => $recipientEmail,
            'subject' => $subject,
            'payload' => $html,
            'status' => 'queued',
            'is_test' => $isTest,
            'context' => $context,
            'queued_at' => now(),
        ]);

        try {
            SendNotificationEmailJob::dispatch(
                $recipientEmail,
                $subject,
                $html,
                $notificationId,
                $context,
                $delivery->id
            )->afterCommit();
        } catch (\Throwable $exception) {
            $delivery->update([
                'status' => 'failed',
                'failure_reason' => 'The delivery could not be queued.',
                'failed_at' => now(),
            ]);
            Log::warning('Notification delivery could not be queued.', [
                'delivery_id' => $delivery->id,
                'notification_id' => $notificationId,
                'event_key' => $eventKey,
                'error' => $exception->getMessage(),
            ]);
        }

        return $delivery->fresh();
    }
}
