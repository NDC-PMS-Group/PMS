<?php

namespace App\Http\Requests\Notification;

class SaveNotificationTemplateDraftRequest extends AdminNotificationRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ];
    }
}
