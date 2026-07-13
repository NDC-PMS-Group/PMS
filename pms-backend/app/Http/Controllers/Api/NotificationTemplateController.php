<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\ManageNotificationTemplateRequest;
use App\Http\Requests\Notification\PreviewNotificationTemplateRequest;
use App\Http\Requests\Notification\SaveNotificationTemplateDraftRequest;
use App\Http\Requests\Notification\SendTestNotificationRequest;
use App\Http\Resources\NotificationDeliveryResource;
use App\Http\Resources\NotificationTemplateResource;
use App\Http\Resources\NotificationTemplateVersionResource;
use App\Models\EmailTemplate;
use App\Models\NotificationTemplateVersion;
use App\Services\NotificationDeliveryService;
use App\Services\NotificationService;
use App\Services\NotificationTemplateService;
use App\Services\NotificationVariableRegistry;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function index(Request $request, NotificationVariableRegistry $registry)
    {
        $this->authorizeAdmin($request);
        $templates = EmailTemplate::query()
            ->with(['eventSettings:id,event_key,template_name', 'draftVersion.author', 'latestPublishedVersion.publisher'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'templates' => NotificationTemplateResource::collection($templates),
            'variables' => $registry->all(),
        ]);
    }

    public function show(Request $request, EmailTemplate $notification_template)
    {
        $this->authorizeAdmin($request);
        $notification_template->load([
            'eventSettings:id,event_key,template_name',
            'draftVersion.author',
            'latestPublishedVersion.publisher',
            'versions.author',
            'versions.publisher',
        ]);

        return new NotificationTemplateResource($notification_template);
    }

    public function saveDraft(
        SaveNotificationTemplateDraftRequest $request,
        EmailTemplate $notification_template,
        NotificationTemplateService $service
    ) {
        $version = $service->saveDraft($notification_template, $request->validated(), $request->user());

        return (new NotificationTemplateVersionResource($version))
            ->additional(['message' => 'Draft saved.']);
    }

    public function preview(
        PreviewNotificationTemplateRequest $request,
        EmailTemplate $notification_template,
        NotificationTemplateService $service
    ) {
        return response()->json(['preview' => $service->preview($notification_template, $request->validated())]);
    }

    public function publish(ManageNotificationTemplateRequest $request, EmailTemplate $notification_template, NotificationTemplateService $service)
    {
        $version = $service->publish($notification_template, $request->user());

        return (new NotificationTemplateVersionResource($version))
            ->additional(['message' => 'Template published.']);
    }

    public function restore(
        ManageNotificationTemplateRequest $request,
        EmailTemplate $notification_template,
        NotificationTemplateVersion $version,
        NotificationTemplateService $service
    ) {
        $draft = $service->restore($notification_template, $version, $request->user());

        return (new NotificationTemplateVersionResource($draft))
            ->additional(['message' => 'Version restored as a new draft.']);
    }

    public function sendTest(
        SendTestNotificationRequest $request,
        EmailTemplate $notification_template,
        NotificationTemplateService $templateService,
        NotificationDeliveryService $deliveryService,
        NotificationService $notificationService
    ) {
        $preview = $templateService->preview($notification_template, $request->validated());
        $version = $notification_template->draftVersion()->first() ?? $notification_template->latestPublishedVersion()->first();
        $delivery = $deliveryService->queueEmail(
            $request->validated('recipient_email'),
            '[TEST] '.$preview['subject'],
            $notificationService->renderEmailHtml($preview, $request->validated('sample_data', [])),
            null,
            $request->user()->id,
            'template_test',
            $version,
            ['template_name' => $notification_template->name, 'requested_by' => $request->user()->id],
            true
        );

        return (new NotificationDeliveryResource($delivery))->additional(['message' => 'Test delivery queued.']);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ((int) $user->default_role_id === 1 || $user->hasPermissionTo('system_settings.update')), 403);
    }
}
