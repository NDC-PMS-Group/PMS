<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationEventSettingRequest;
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

    public function update(UpdateNotificationEventSettingRequest $request, NotificationEventSetting $notification_event_setting)
    {
        $validated = $request->validated();

        $template = isset($validated['email_template_id'])
            ? EmailTemplate::query()->find($validated['email_template_id'])
            : EmailTemplate::query()->where('name', $validated['template_name'] ?? null)->first();

        if ($validated['email_enabled'] && $template) {
            abort_unless($template->is_active && $template->latestPublishedVersion()->exists(), 422, 'Choose an active template with a published version.');
        }

        $validated['email_template_id'] = $template?->id;
        $validated['template_name'] = $template?->name;

        $notification_event_setting->update(array_merge($validated, [
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Notification rule updated.',
            'event' => $notification_event_setting->fresh('template'),
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
