<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationPreferencesRequest;
use App\Models\NotificationEventSetting;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request)
    {
        $saved = $request->user()->notificationPreferences()->get()->keyBy('notification_type');
        $preferences = NotificationEventSetting::query()
            ->orderBy('category')
            ->orderBy('label')
            ->get()
            ->map(function (NotificationEventSetting $event) use ($saved) {
                $preference = $saved->get($event->event_key);

                return [
                    'notification_type' => $event->event_key,
                    'label' => $event->label,
                    'category' => $event->category,
                    'email_enabled' => $preference?->email_enabled ?? true,
                    'in_app_enabled' => $preference?->in_app_enabled ?? true,
                    'email_available' => $event->email_enabled,
                    'in_app_available' => $event->in_app_enabled,
                ];
            });

        return response()->json(['preferences' => $preferences]);
    }

    public function update(UpdateNotificationPreferencesRequest $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('preferences') as $preference) {
                NotificationPreference::query()->updateOrCreate(
                    [
                        'user_id' => $request->user()->id,
                        'notification_type' => $preference['notification_type'],
                    ],
                    [
                        'email_enabled' => $preference['email_enabled'],
                        'in_app_enabled' => $preference['in_app_enabled'],
                    ]
                );
            }
        });

        return response()->json(['message' => 'Notification preferences updated.']);
    }
}
