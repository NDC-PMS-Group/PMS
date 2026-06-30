<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\NotificationEventSetting;
use Illuminate\Http\Request;

class NotificationEventSettingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'events' => NotificationEventSetting::query()
                ->orderBy('category')
                ->orderBy('label')
                ->get()
                ->groupBy('category'),
            'templates' => EmailTemplate::query()
                ->orderBy('name')
                ->get(['name', 'subject', 'is_active']),
        ]);
    }

    public function update(Request $request, NotificationEventSetting $notification_event_setting)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'in_app_enabled' => 'required|boolean',
            'email_enabled' => 'required|boolean',
            'template_name' => 'nullable|string|exists:email_templates,name',
        ]);

        $notification_event_setting->update(array_merge($validated, [
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Notification rule updated.',
            'event' => $notification_event_setting->fresh(),
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user && ((int) $user->default_role_id === 1 || $user->hasPermissionTo('system_settings.update')),
            403,
            'Unauthorized to manage notification rules.'
        );
    }
}
