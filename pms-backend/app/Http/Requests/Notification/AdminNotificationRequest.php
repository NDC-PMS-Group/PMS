<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

abstract class AdminNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ((int) $user->default_role_id === 1 || $user->hasPermissionTo('system_settings.update'));
    }
}
