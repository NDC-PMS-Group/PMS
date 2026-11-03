<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

abstract class AdminNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->hasPermissionTo('system_settings.update');
    }
}
