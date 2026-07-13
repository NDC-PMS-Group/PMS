<?php

namespace App\Http\Requests\Notification;

class UpdateNotificationEventSettingRequest extends AdminNotificationRequest
{
    public function rules(): array
    {
        return [
            'in_app_enabled' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
            'template_name' => ['nullable', 'string', 'max:100', 'exists:email_templates,name'],
            'email_template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
        ];
    }
}
