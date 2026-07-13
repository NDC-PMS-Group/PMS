<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'max:100'],
            'preferences.*.notification_type' => [
                'required',
                'string',
                Rule::exists('notification_event_settings', 'event_key'),
                'distinct',
            ],
            'preferences.*.email_enabled' => ['required', 'boolean'],
            'preferences.*.in_app_enabled' => ['required', 'boolean'],
        ];
    }
}
