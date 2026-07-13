<?php

namespace App\Http\Requests\Notification;

class PreviewNotificationTemplateRequest extends AdminNotificationRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'sample_data' => ['sometimes', 'array'],
            'sample_data.*' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
