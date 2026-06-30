<?php

namespace App\Services;

use App\Jobs\SendNotificationEmailJob;
use App\Models\EmailTemplate;
use App\Models\Notification;
use App\Models\NotificationEventSetting;
use App\Models\NotificationPreference;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function notifyUser(
        User $user,
        string $type,
        string $title,
        string $message,
        ?Model $relatedEntity = null,
        ?string $emailTemplate = null,
        array $templateData = []
    ): ?Notification {
        // Auto-inject context variables based on user and related entity
        $autoinjected = [
            'user_name' => $user->full_name,
            'approver_name' => $user->full_name,
            'recipient_name' => $user->full_name,
            'email' => $user->email,
            'username' => $user->username,
            'changed_by' => auth()->user()?->full_name ?? 'System',
        ];

        if ($relatedEntity instanceof \App\Models\Project) {
            $autoinjected['project_title'] = $relatedEntity->title;
            $autoinjected['project_name'] = $relatedEntity->title;
            $autoinjected['project_code'] = $relatedEntity->project_code;
            $autoinjected['current_step'] = $relatedEntity->currentStage?->step_name 
                ?? $relatedEntity->currentStage?->name 
                ?? 'SOI Evaluation';
            $autoinjected['proponent_name'] = $relatedEntity->proponent_name;
        } elseif ($relatedEntity instanceof \App\Models\User) {
            $autoinjected['user_name'] = $relatedEntity->full_name;
            $autoinjected['username'] = $relatedEntity->username;
            $autoinjected['email'] = $relatedEntity->email;
            $autoinjected['organization_name'] = $relatedEntity->organization_name 
                ?? $relatedEntity->company_name 
                ?? 'N/A';
            $autoinjected['position'] = $relatedEntity->position ?? 'N/A';
            $autoinjected['created_at'] = $relatedEntity->created_at 
                ? $relatedEntity->created_at->format('Y-m-d H:i') 
                : now()->format('Y-m-d H:i');
        } elseif ($relatedEntity instanceof \App\Models\Document) {
            $autoinjected['document_title'] = $relatedEntity->title;
            $autoinjected['document_name'] = $relatedEntity->title;
            $autoinjected['submitted_at'] = $relatedEntity->submitted_at 
                ? $relatedEntity->submitted_at->format('Y-m-d H:i') 
                : now()->format('Y-m-d H:i');
            
            if ($relatedEntity->project) {
                $autoinjected['project_title'] = $relatedEntity->project->title;
                $autoinjected['project_name'] = $relatedEntity->project->title;
                $autoinjected['project_code'] = $relatedEntity->project->project_code;
            }
        } elseif ($relatedEntity instanceof \App\Models\Task) {
            $autoinjected['task_title'] = $relatedEntity->title;
            $autoinjected['task_description'] = $relatedEntity->description ?? 'No description provided.';
            $autoinjected['due_date'] = $relatedEntity->due_date 
                ? (\DateTime::createFromFormat('Y-m-d', $relatedEntity->due_date) ? $relatedEntity->due_date : (is_string($relatedEntity->due_date) ? $relatedEntity->due_date : $relatedEntity->due_date->format('Y-m-d')))
                : 'N/A';
            $autoinjected['priority'] = $relatedEntity->priority ?? 'Medium';
            
            if ($relatedEntity->project) {
                $autoinjected['project_title'] = $relatedEntity->project->title;
                $autoinjected['project_name'] = $relatedEntity->project->title;
                $autoinjected['project_code'] = $relatedEntity->project->project_code;
            }
        } elseif ($relatedEntity instanceof \App\Models\ProjectApproval) {
            if ($relatedEntity->project) {
                $autoinjected['project_title'] = $relatedEntity->project->title;
                $autoinjected['project_name'] = $relatedEntity->project->title;
                $autoinjected['project_code'] = $relatedEntity->project->project_code;
            }
            $autoinjected['current_step'] = $relatedEntity->currentStep?->step_name ?? 'Review';
            $autoinjected['reviewer_role'] = $relatedEntity->currentStep?->role?->name ?? 'Reviewer';
        }

        $templateData = array_merge($autoinjected, $templateData);

        $eventSetting = $this->eventSetting($type);
        $preference = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('notification_type', $type)
            ->first();

        if ($eventSetting && !$eventSetting->in_app_enabled && !$eventSetting->email_enabled) {
            return null;
        }

        if ($preference && !$preference->in_app_enabled && !$preference->email_enabled) {
            return null;
        }

        $resolvedTemplate = $emailTemplate ?: $eventSetting?->template_name;
        $createInApp = (!$eventSetting || $eventSetting->in_app_enabled)
            && (!$preference || $preference->in_app_enabled);

        if (!$createInApp) {
            if ((!$eventSetting || $eventSetting->email_enabled) && (!$preference || $preference->email_enabled)) {
                $this->sendEmailToUser($user, $title, $message, $resolvedTemplate, $templateData);
            }

            return null;
        }

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_entity_type' => $relatedEntity ? get_class($relatedEntity) : null,
            'related_entity_id' => $relatedEntity?->getKey(),
            'is_read' => false,
            'is_email_sent' => false,
            'created_at' => now(),
        ]);

        if ((!$eventSetting || $eventSetting->email_enabled) && (!$preference || $preference->email_enabled)) {
            $this->sendEmailIfEnabled($notification, $user, $title, $message, $resolvedTemplate, $templateData);
        }

        return $notification;
    }

    public function notifyUsers(
        iterable $users,
        string $type,
        string $title,
        string $message,
        ?Model $relatedEntity = null,
        ?string $emailTemplate = null,
        array $templateData = []
    ): void {
        $this->uniqueActiveUsers($users)->each(function (User $user) use (
            $type,
            $title,
            $message,
            $relatedEntity,
            $emailTemplate,
            $templateData
        ) {
            $this->notifyUser(
                $user,
                $type,
                $title,
                $message,
                $relatedEntity,
                $emailTemplate,
                array_merge($templateData, [
                    'user_name' => $this->recipientDisplayName($user),
                    'approver_name' => $user->full_name,
                ])
            );
        });
    }

    public function projectStakeholders(Project $project): Collection
    {
        $project->loadMissing([
            'creator',
            'projectOfficer',
            'workgroupHead',
            'members.user',
        ]);

        return $this->uniqueActiveUsers(collect([
            $project->creator,
            $project->projectOfficer,
            $project->workgroupHead,
        ])->merge($project->members->whereNull('removed_at')->pluck('user')));
    }

    public function internalProjectStakeholders(Project $project, ?User $except = null): Collection
    {
        return $this->projectStakeholders($project)
            ->reject(fn (User $user) => $this->isProponentUser($user, $project))
            ->when($except, fn (Collection $users) => $users->reject(fn (User $user) => (int) $user->id === (int) $except->id))
            ->unique('id')
            ->values();
    }

    public function proponentRecipients(Project $project, ?User $except = null): Collection
    {
        $project->loadMissing(['creator', 'proponentUser']);

        return $this->uniqueActiveUsers(collect([
            $project->proponentUser,
            $project->creator,
        ]))
            ->filter(fn (User $user) => $this->isProponentUser($user, $project))
            ->when($except, fn (Collection $users) => $users->reject(fn (User $user) => (int) $user->id === (int) $except->id))
            ->unique('id')
            ->values();
    }

    public function notifyProjectStakeholders(
        Project $project,
        string $type,
        string $title,
        string $message,
        ?string $emailTemplate = null,
        array $templateData = []
    ): void {
        $this->notifyUsers(
            $this->projectStakeholders($project),
            $type,
            $title,
            $message,
            $project,
            $emailTemplate,
            $templateData
        );

        $this->sendExternalProponentEmail(
            $project,
            $title,
            $message,
            $emailTemplate,
            array_merge($templateData, ['event_key' => $type])
        );
    }

    public function notifyProjectProponent(
        Project $project,
        string $type,
        string $title,
        string $message,
        ?string $emailTemplate = null,
        array $templateData = []
    ): void {
        $users = $this->proponentRecipients($project);

        $this->notifyUsers(
            $users,
            $type,
            $title,
            $message,
            $project,
            $emailTemplate,
            $templateData
        );

        $this->sendExternalProponentEmail(
            $project,
            $title,
            $message,
            $emailTemplate,
            array_merge($templateData, ['event_key' => $type])
        );
    }

    private function uniqueActiveUsers(iterable $users): Collection
    {
        $collection = $users instanceof Collection || $users instanceof EloquentCollection || is_array($users)
            ? collect($users)
            : collect(iterator_to_array($users));

        return $collection
            ->filter(fn ($user) => $user instanceof User && $user->is_active)
            ->unique('id')
            ->values();
    }

    private function sendEmailIfEnabled(
        Notification $notification,
        User $user,
        string $title,
        string $message,
        ?string $templateName,
        array $templateData
    ): void {
        if (!$this->emailNotificationsEnabled() || empty($user->email)) {
            return;
        }

        $payload = ['subject' => $title, 'body' => $message];
        if ($templateName) {
            $template = EmailTemplate::active()->where('name', $templateName)->first();
            if ($template) {
                $payload = $template->render($templateData);
            }
        }

        $this->queueEmail(
            (string) $user->email,
            $payload,
            $templateData,
            $notification->id,
            [
                'user_id' => $user->id,
                'event_type' => $notification->type,
                'related_entity_type' => $notification->related_entity_type,
                'related_entity_id' => $notification->related_entity_id,
            ],
            'Email notification was not queued.'
        );
    }

    private function sendExternalProponentEmail(
        Project $project,
        string $title,
        string $message,
        ?string $templateName,
        array $templateData
    ): void {
        $email = trim((string) $project->proponent_email);
        $eventSetting = $this->eventSetting((string) ($templateData['event_key'] ?? ''));
        if (
            !$this->emailNotificationsEnabled()
            || $email === ''
            || ($eventSetting && !$eventSetting->email_enabled)
        ) {
            return;
        }

        $hasActiveUserRecipient = User::active()->where('email', $email)->exists();
        if ($hasActiveUserRecipient) {
            return;
        }

        $payload = ['subject' => $title, 'body' => $message];
        if ($templateName) {
            $template = EmailTemplate::active()->where('name', $templateName)->first();
            if ($template) {
                $payload = $template->render(array_merge($templateData, [
                    'user_name' => $project->proponent_name ?: 'Proponent',
                ]));
            }
        }

        $this->queueEmail(
            $email,
            $payload,
            $templateData,
            null,
            [
                'project_id' => $project->id,
                'event_type' => $templateData['event_key'] ?? null,
            ],
            'External proponent email notification was not queued.'
        );
    }

    private function emailNotificationsEnabled(): bool
    {
        $setting = SystemSetting::where('setting_key', 'enable_email_notifications')->first();

        if (!$setting) {
            return true;
        }

        return (bool) $setting->value;
    }

    private function eventSetting(string $type): ?NotificationEventSetting
    {
        if ($type === '') {
            return null;
        }

        return NotificationEventSetting::query()->where('event_key', $type)->first();
    }

    private function sendEmailToUser(
        User $user,
        string $title,
        string $message,
        ?string $templateName,
        array $templateData
    ): void {
        if (!$this->emailNotificationsEnabled() || empty($user->email)) {
            return;
        }

        $payload = ['subject' => $title, 'body' => $message];
        if ($templateName) {
            $template = EmailTemplate::active()->where('name', $templateName)->first();
            if ($template) {
                $payload = $template->render($templateData);
            }
        }

        $this->queueEmail(
            (string) $user->email,
            $payload,
            $templateData,
            null,
            [
                'user_id' => $user->id,
                'event_title' => $title,
            ],
            'Email-only notification was not queued.'
        );
    }

    private function isProponentUser(User $user, Project $project): bool
    {
        $proponentEmail = trim((string) $project->proponent_email);

        return $user->hasRole('Proponent')
            || ((int) $user->default_role_id === 7)
            || ($proponentEmail !== '' && strcasecmp((string) $user->email, $proponentEmail) === 0);
    }

    private function recipientDisplayName(User $user): string
    {
        if ($user->hasRole('Proponent') || (int) $user->default_role_id === 7) {
            $organization = trim((string) $user->organization_name);
            if ($organization !== '') {
                return $organization;
            }
        }

        return trim((string) $user->full_name) ?: 'there';
    }

    private function queueEmail(
        string $recipientEmail,
        array $payload,
        array $templateData,
        ?int $notificationId,
        array $context,
        string $failureMessage
    ): void {
        try {
            SendNotificationEmailJob::dispatch(
                $recipientEmail,
                (string) ($payload['subject'] ?? 'NDC PMS Notification'),
                $this->renderHtmlEmail($payload, $templateData),
                $notificationId,
                $context
            );
        } catch (\Throwable $exception) {
            Log::warning($failureMessage, array_merge($context, [
                'notification_id' => $notificationId,
                'recipient_email' => $recipientEmail,
                'error' => $exception->getMessage(),
            ]));
        }
    }

    private function renderHtmlEmail(array $payload, array $templateData = []): string
    {
        $subject = e((string) ($payload['subject'] ?? 'NDC PMS Notification'));
        $body = trim((string) ($payload['body'] ?? ''));
        $actionUrl = trim((string) ($templateData['action_url'] ?? ''));
        $actionLabel = trim((string) ($templateData['action_label'] ?? 'Open in NDC PMS'));

        $paragraphs = collect(preg_split("/\n{2,}/", $body) ?: [])
            ->map(fn ($paragraph) => trim((string) $paragraph))
            ->filter()
            ->map(function (string $paragraph): string {
                $lines = array_map('trim', preg_split("/\n/", $paragraph) ?: []);
                $isDetails = count($lines) > 1 && collect($lines)->contains(fn ($line) => str_contains($line, ':'));

                if ($isDetails) {
                    $rows = collect($lines)->map(function (string $line): string {
                        [$label, $value] = array_pad(explode(':', $line, 2), 2, '');
                        return '<tr><td style="padding:7px 12px;color:#64748b;font-size:13px;border-bottom:1px solid #e5e7eb;">'
                            . e(trim($label))
                            . '</td><td style="padding:7px 12px;color:#111827;font-size:13px;font-weight:700;border-bottom:1px solid #e5e7eb;">'
                            . e(trim($value))
                            . '</td></tr>';
                    })->implode('');

                    return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin:14px 0;background:#ffffff;">'
                        . $rows
                        . '</table>';
                }

                return '<p style="margin:0 0 14px;color:#334155;font-size:15px;line-height:1.65;">'
                    . nl2br(e($paragraph))
                    . '</p>';
            })
            ->implode('');

        $button = $actionUrl !== ''
            ? '<div style="margin:24px 0 10px;"><a href="' . e($actionUrl) . '" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;padding:12px 18px;font-weight:800;font-size:14px;">' . e($actionLabel) . '</a></div>'
            : '';

        return '<!doctype html><html><body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">'
            . '<div style="max-width:680px;margin:0 auto;padding:28px 16px;">'
            . '<div style="background:#0f172a;border-radius:14px 14px 0 0;padding:22px 26px;">'
            . '<div style="color:#93c5fd;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;">NDC PMS</div>'
            . '<h1 style="margin:8px 0 0;color:#ffffff;font-size:22px;line-height:1.3;">' . $subject . '</h1>'
            . '</div>'
            . '<div style="background:#ffffff;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 14px 14px;padding:26px;">'
            . $paragraphs
            . $button
            . '<p style="margin:22px 0 0;color:#64748b;font-size:12px;line-height:1.55;">This message was sent by the NDC Project Management System. Use the button above to open the related record directly.</p>'
            . '</div></div></body></html>';
    }
}
