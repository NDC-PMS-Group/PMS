<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationDeliveryResource;
use App\Models\NotificationDelivery;
use App\Jobs\SendNotificationEmailJob;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationDeliveryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['queued', 'processing', 'sent', 'failed', 'skipped', 'cancelled'])],
            'channel' => ['nullable', Rule::in(['email', 'in_app'])],
            'event_key' => ['nullable', 'string', 'max:80'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $deliveries = NotificationDelivery::query()
            ->with('templateVersion:id,version')
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['channel'] ?? null, fn ($query, $channel) => $query->where('channel', $channel))
            ->when($validated['event_key'] ?? null, fn ($query, $event) => $query->where('event_key', $event))
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('subject', 'like', '%'.$search.'%')
                        ->orWhere('event_key', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->paginate($validated['per_page'] ?? 25);

        return NotificationDeliveryResource::collection($deliveries);
    }

    public function overview(Request $request)
    {
        $this->authorizeAdmin($request);
        $base = NotificationDelivery::query()->where('created_at', '>=', now()->subDay());

        return response()->json([
            'period' => '24h',
            'total' => (clone $base)->count(),
            'queued' => (clone $base)->where('status', 'queued')->count(),
            'sent' => (clone $base)->where('status', 'sent')->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
        ]);
    }

    public function show(Request $request, NotificationDelivery $notificationDelivery)
    {
        $this->authorizeAdmin($request);

        return new NotificationDeliveryResource($notificationDelivery->load('templateVersion:id,version'));
    }

    public function retry(Request $request, NotificationDelivery $notificationDelivery)
    {
        $this->authorizeAdmin($request);
        abort_unless($notificationDelivery->status === 'failed' && $notificationDelivery->payload, 409, 'Only failed email deliveries with a stored payload can be retried.');

        $notificationDelivery->update([
            'status' => 'queued',
            'failure_reason' => null,
            'failed_at' => null,
            'queued_at' => now(),
        ]);

        SendNotificationEmailJob::dispatch(
            $notificationDelivery->recipient_address,
            $notificationDelivery->subject,
            $notificationDelivery->payload,
            $notificationDelivery->notification_id,
            ['retry_delivery_id' => $notificationDelivery->id],
            $notificationDelivery->id,
        )->afterCommit();

        return (new NotificationDeliveryResource($notificationDelivery->fresh()))
            ->additional(['message' => 'Delivery queued for retry.']);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ((int) $user->default_role_id === 1 || $user->hasPermissionTo('system_settings.update')), 403);
    }
}
