<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $subject,
        public readonly string $html,
        public readonly ?int $notificationId = null,
        public readonly array $context = [],
        public readonly ?int $deliveryId = null
    ) {}

    public function handle(): void
    {
        if ($this->deliveryId) {
            NotificationDelivery::query()->whereKey($this->deliveryId)->update(['status' => 'processing']);
            NotificationDelivery::query()->whereKey($this->deliveryId)->increment('attempts');
        }

        Mail::html($this->html, function ($mail) {
            $mail->to($this->recipientEmail)->subject($this->subject);
        });

        if ($this->notificationId) {
            Notification::query()
                ->whereKey($this->notificationId)
                ->update([
                    'is_email_sent' => true,
                    'email_sent_at' => now(),
                ]);
        }

        if ($this->deliveryId) {
            NotificationDelivery::query()->whereKey($this->deliveryId)->update([
                'status' => 'sent',
                'sent_at' => now(),
                'failure_reason' => null,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->deliveryId) {
            NotificationDelivery::query()->whereKey($this->deliveryId)->update([
                'status' => 'failed',
                'failure_reason' => 'Email delivery failed after all retry attempts.',
                'failed_at' => now(),
            ]);
        }

        Log::warning('Queued email notification was not sent.', array_merge($this->context, [
            'delivery_id' => $this->deliveryId,
            'notification_id' => $this->notificationId,
            'subject' => $this->subject,
            'error' => $exception->getMessage(),
        ]));
    }
}
