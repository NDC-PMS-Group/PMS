<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AlertReachedTaskDeadlines extends Command
{
    protected $signature = 'tasks:alert-reached-deadlines';

    protected $description = 'Send upcoming, reached, and overdue task deadline reminders';

    public function handle(NotificationService $notifications): int
    {
        $administrators = User::active()
            ->where(function ($query) {
                $query->where('default_role_id', 1)
                    ->orWhereHas('defaultRole', fn ($roleQuery) => $roleQuery
                        ->whereRaw('LOWER(name) in (?, ?)', ['superadmin', 'admin']));
            })
            ->get();

        $sent = 0;
        Task::query()
            ->active()
            ->with(['project.projectOfficer', 'project.workgroupHead', 'assignedTo'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', today()->addDays(3))
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('id')
            ->chunkById(100, function ($tasks) use ($administrators, $notifications, &$sent) {
                foreach ($tasks as $task) {
                    $reminder = $this->reminderFor($task);
                    if (! $reminder) {
                        continue;
                    }

                    $recipients = $this->recipients($task, $administrators);
                    if ($recipients->isEmpty()) {
                        continue;
                    }

                    $reserved = DB::transaction(function () use ($task, $reminder) {
                        $locked = Task::query()->lockForUpdate()->find($task->id);
                        if (! $locked
                            || ! $locked->due_date
                            || in_array($locked->status, ['completed', 'cancelled'], true)) {
                            return false;
                        }

                        $inserted = DB::table('task_deadline_notifications')->insertOrIgnore([
                            'task_id' => $locked->id,
                            'due_date' => $locked->due_date->toDateString(),
                            'reminder_key' => $reminder['key'],
                            'event_type' => $reminder['event'],
                            'days_remaining' => $reminder['days_remaining'],
                            'sent_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        if (! $inserted) {
                            return false;
                        }

                        if ($reminder['days_remaining'] === 0) {
                            $locked->update([
                                'deadline_alerted_for' => $locked->due_date,
                                'deadline_alerted_at' => now(),
                            ]);

                            TaskStatusHistory::create([
                                'task_id' => $locked->id,
                                'from_status' => $locked->status,
                                'to_status' => $locked->status,
                                'from_progress' => $locked->progress_percentage,
                                'to_progress' => $locked->progress_percentage,
                                'previous_due_date' => $locked->due_date,
                                'new_due_date' => $locked->due_date,
                                'changed_by' => null,
                                'event_type' => 'deadline_reached',
                                'notes' => 'The task reached its configured timeline deadline.',
                                'reason' => 'Automated deadline alert.',
                                'changed_at' => now(),
                            ]);
                        }

                        return true;
                    });

                    if (! $reserved) {
                        continue;
                    }

                    $freshTask = $task->fresh(['project']);
                    try {
                        $notifications->notifyUsers(
                            $recipients,
                            $reminder['event'],
                            "{$reminder['timing']}: {$freshTask->title}",
                            $this->message($freshTask, $reminder),
                            $freshTask,
                            'task_deadline_reminder',
                            [
                                'task_title' => $freshTask->title,
                                'project_title' => $freshTask->project?->title ?: 'Project',
                                'project_code' => $freshTask->project?->project_code ?: 'N/A',
                                'due_date' => $freshTask->due_date?->toFormattedDateString(),
                                'timing' => $reminder['timing'],
                                'action_url' => rtrim((string) config('app.frontend_url'), '/') . '/projects?project_id=' . $freshTask->project_id . '&tab=work-plan&task_id=' . $freshTask->id,
                                'action_label' => 'Open Work Plan',
                            ]
                        );
                    } catch (\Throwable $exception) {
                        Log::warning('Task deadline reminder failed.', [
                            'task_id' => $freshTask->id,
                            'reminder_key' => $reminder['key'],
                            'error' => $exception->getMessage(),
                        ]);
                    }

                    $sent++;
                }
            });

        $this->info("Created {$sent} task deadline reminder(s).");

        return self::SUCCESS;
    }

    private function reminderFor(Task $task): ?array
    {
        $daysRemaining = (int) today()->diffInDays($task->due_date, false);

        if (in_array($daysRemaining, [3, 1, 0], true)) {
            return [
                'key' => "due_{$daysRemaining}",
                'event' => $daysRemaining === 0 ? 'task_deadline_reached' : 'task_deadline_due_soon',
                'days_remaining' => $daysRemaining,
                'timing' => match ($daysRemaining) {
                    3 => 'Due in 3 days',
                    1 => 'Due tomorrow',
                    default => 'Deadline reached',
                },
            ];
        }

        $daysOverdue = abs($daysRemaining);
        if ($daysRemaining < 0 && ($daysOverdue === 1 || ($daysOverdue >= 7 && $daysOverdue % 7 === 0))) {
            return [
                'key' => "overdue_{$daysOverdue}",
                'event' => 'task_deadline_overdue',
                'days_remaining' => $daysRemaining,
                'timing' => 'Overdue by ' . $daysOverdue . ' ' . ($daysOverdue === 1 ? 'day' : 'days'),
            ];
        }

        return null;
    }

    private function recipients(Task $task, Collection $administrators): Collection
    {
        return collect([
            $task->assignedTo,
            $task->project?->projectOfficer,
            $task->project?->workgroupHead,
        ])->merge($administrators)
            ->filter(fn ($user) => $user instanceof User && $user->is_active)
            ->unique('id')
            ->values();
    }

    private function message(Task $task, array $reminder): string
    {
        $project = $task->project?->title ?: 'the related project';

        return "{$task->title} in {$project} is {$reminder['timing']}. Due {$task->due_date?->toFormattedDateString()}. Open the work plan to complete the task or resolve its deadline.";
    }
}
