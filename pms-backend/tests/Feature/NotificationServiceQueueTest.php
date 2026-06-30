<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationEmailJob;
use App\Models\Industry;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\ProjectType;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationServiceQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_service_creates_in_app_notification_and_queues_email(): void
    {
        Queue::fake();

        $user = $this->createUser('recipient@example.com');

        $notification = app(NotificationService::class)->notifyUser(
            $user,
            'task_completed',
            'Task completed: Review package',
            'System completed Review package.'
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertFalse($notification->refresh()->is_email_sent);

        Queue::assertPushed(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($notification) {
            return $job->recipientEmail === 'recipient@example.com'
                && $job->subject === 'Task completed: Review package'
                && $job->notificationId === $notification->id
                && ($job->context['event_type'] ?? null) === 'task_completed';
        });
    }

    public function test_external_project_proponent_email_is_queued(): void
    {
        Queue::fake();

        $creator = $this->createUser('creator@example.com');
        $project = $this->createProject($creator, 'external-proponent@example.com');

        app(NotificationService::class)->notifyProjectProponent(
            $project,
            'requirement_status_changed',
            'SOI Requirement Update',
            'A requirement was requested.'
        );

        Queue::assertPushed(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($project) {
            return $job->recipientEmail === 'external-proponent@example.com'
                && $job->subject === 'SOI Requirement Update'
                && $job->notificationId === null
                && ($job->context['project_id'] ?? null) === $project->id;
        });
    }

    public function test_queued_email_job_sends_mail_and_marks_notification_sent(): void
    {
        Mail::shouldReceive('html')
            ->once()
            ->with(\Mockery::type('string'), \Mockery::type('Closure'));

        $user = $this->createUser('recipient@example.com');
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'task_completed',
            'title' => 'Task completed',
            'message' => 'Task completed.',
            'is_read' => false,
            'is_email_sent' => false,
            'created_at' => now(),
        ]);

        (new SendNotificationEmailJob(
            'recipient@example.com',
            'Task completed',
            '<p>Task completed.</p>',
            $notification->id,
            ['event_type' => 'task_completed']
        ))->handle();

        $this->assertTrue($notification->refresh()->is_email_sent);
        $this->assertNotNull($notification->email_sent_at);
    }

    public function test_email_verification_notification_is_queued(): void
    {
        Queue::fake();

        $user = $this->createUser('verify@example.com', false);

        $user->sendEmailVerificationNotification();

        Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job) {
            return $job->notification instanceof QueuedVerifyEmail;
        });
    }

    private function createUser(string $email, bool $active = true): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'Project Officer'],
            ['description' => 'Project Officer', 'is_system_role' => true]
        );

        return User::create([
            'username' => str_replace(['@', '.'], '-', $email),
            'email' => $email,
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Queue',
            'last_name' => 'Tester',
            'default_role_id' => $role->id,
            'is_active' => $active,
        ]);
    }

    private function createProject(User $creator, string $proponentEmail): Project
    {
        $stage = ProjectStage::create([
            'name' => 'Intake',
            'sequence_order' => 1,
            'description' => 'Intake',
            'is_active' => true,
        ]);
        $status = ProjectStatus::create(['name' => 'LOI Received', 'color_code' => '#2563eb', 'is_active' => true]);
        $type = ProjectType::create(['name' => 'Infrastructure', 'description' => 'Infrastructure']);
        $industry = Industry::create(['name' => 'Technology', 'description' => 'Technology']);
        $sector = Sector::create(['name' => 'Public', 'description' => 'Public']);

        return Project::create([
            'project_code' => 'BDG-2026-QUEUE',
            'title' => 'Queued Email Project',
            'description' => 'Project used for queue tests',
            'project_type_id' => $type->id,
            'industry_id' => $industry->id,
            'sector_id' => $sector->id,
            'currency' => 'PHP',
            'current_stage_id' => $stage->id,
            'status_id' => $status->id,
            'proposal_date' => now()->toDateString(),
            'proponent_email' => $proponentEmail,
            'proponent_name' => 'External Proponent',
            'is_svf' => false,
            'is_archived' => false,
            'is_deleted' => false,
            'created_by' => $creator->id,
        ]);
    }
}
