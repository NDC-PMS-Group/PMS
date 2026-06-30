<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mail:test {recipient=alvindalejoyosa30@gmail.com}', function (string $recipient) {
    Mail::raw('NDC PMS local email test. If you received this, SMTP delivery is working.', function ($message) use ($recipient) {
        $message->to($recipient)->subject('NDC PMS SMTP test');
    });

    $this->info("Test email queued for {$recipient}.");
})->purpose('Send a local SMTP test email');
