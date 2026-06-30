<?php

namespace App\Jobs;

use App\Models\Notification;
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
        public readonly array $context = []
    ) {
    }

    public function handle(): void
    {
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
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('Queued email notification was not sent.', array_merge($this->context, [
            'notification_id' => $this->notificationId,
            'recipient_email' => $this->recipientEmail,
            'subject' => $this->subject,
            'error' => $exception->getMessage(),
        ]));
    }
}
