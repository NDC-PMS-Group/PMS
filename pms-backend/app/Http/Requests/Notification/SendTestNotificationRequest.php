<?php

namespace App\Http\Requests\Notification;

class SendTestNotificationRequest extends AdminNotificationRequest
{
    public function rules(): array
    {
        return [
            'recipient_email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'sample_data' => ['sometimes', 'array'],
            'sample_data.*' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
