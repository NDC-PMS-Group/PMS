<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\Notification;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
    ): Notification {
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

        $this->sendEmailIfEnabled($notification, $user, $title, $message, $emailTemplate, $templateData);

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
                    'user_name' => $user->full_name,
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

        try {
            Mail::raw($payload['body'], function ($mail) use ($user, $payload) {
                $mail->to($user->email)->subject($payload['subject']);
            });

            $notification->update([
                'is_email_sent' => true,
                'email_sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Email notification was not sent.', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function emailNotificationsEnabled(): bool
    {
        $setting = SystemSetting::where('setting_key', 'enable_email_notifications')->first();

        if (!$setting) {
            return true;
        }

        return (bool) $setting->value;
    }
}
