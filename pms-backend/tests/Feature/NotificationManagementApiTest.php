<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationEmailJob;
use App\Models\EmailTemplate;
use App\Models\NotificationDelivery;
use App\Models\NotificationEventSetting;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\NotificationEventSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_preview_and_publish_a_plain_text_template_version(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $template = EmailTemplate::query()->where('name', 'approval_request')->firstOrFail();

        $this->putJson("/api/notification-templates/{$template->id}/draft", [
            'subject' => 'Approval: {{project_title}}',
            'body' => "Hello {{approver_name}},\n\nReview {{project_title}} at {{current_step}}.",
        ])->assertOk()->assertJsonPath('data.status', 'draft');

        $this->postJson("/api/notification-templates/{$template->id}/preview", [
            'subject' => 'Approval: {{project_title}}',
            'body' => 'Hello {{approver_name}}',
            'sample_data' => ['project_title' => 'Budget review', 'approver_name' => 'Maria'],
        ])->assertOk()
            ->assertJsonPath('preview.subject', 'Approval: Budget review')
            ->assertJsonPath('preview.body', 'Hello Maria');

        $this->postJson("/api/notification-templates/{$template->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $template->refresh();
        $this->assertSame('Approval: {{project_title}}', $template->subject);
        $this->assertSame(['approver_name', 'current_step', 'project_title'], $template->variables);
        $this->assertDatabaseHas('notification_template_versions', [
            'email_template_id' => $template->id,
            'version' => 2,
            'status' => 'published',
            'published_by' => $admin->id,
        ]);
    }

    public function test_publish_validation_rejects_html_and_unknown_variables(): void
    {
        Sanctum::actingAs($this->admin());
        $template = EmailTemplate::query()->firstOrFail();

        $this->putJson("/api/notification-templates/{$template->id}/draft", [
            'subject' => 'Alert {{unsupported_token}}',
            'body' => '<strong>Unsafe</strong>',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['variables', 'body']);
    }

    public function test_restore_creates_a_new_draft_without_changing_published_content(): void
    {
        Sanctum::actingAs($this->admin());
        $template = EmailTemplate::query()->where('name', 'approval_request')->firstOrFail();
        $published = $template->latestPublishedVersion()->firstOrFail();
        $publishedSubject = $template->subject;

        $this->postJson("/api/notification-templates/{$template->id}/versions/{$published->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.restored_from_id', $published->id);

        $this->assertSame($publishedSubject, $template->fresh()->subject);
        $this->assertDatabaseHas('notification_template_versions', [
            'email_template_id' => $template->id,
            'status' => 'draft',
            'restored_from_id' => $published->id,
        ]);
    }

    public function test_test_delivery_is_queued_and_activity_masks_recipient(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin());
        $template = EmailTemplate::query()->where('name', 'approval_request')->firstOrFail();

        $this->postJson("/api/notification-templates/{$template->id}/test", [
            'recipient_email' => 'administrator@example.com',
        ])->assertOk()
            ->assertJsonPath('data.recipient', 'ad***********@example.com')
            ->assertJsonPath('data.is_test', true)
            ->assertJsonPath('data.status', 'queued');

        $delivery = NotificationDelivery::query()->firstOrFail();
        $this->assertSame('administrator@example.com', $delivery->recipient_address);
        Queue::assertPushed(SendNotificationEmailJob::class, fn (SendNotificationEmailJob $job) => $job->deliveryId === $delivery->id);

        $this->getJson('/api/notification-deliveries')
            ->assertOk()
            ->assertJsonPath('data.0.recipient', 'ad***********@example.com');
    }

    public function test_user_can_manage_only_their_own_notification_preferences(): void
    {
        $user = $this->admin();
        Sanctum::actingAs($user);
        $event = NotificationEventSetting::query()->firstOrFail();

        $this->putJson('/api/notification-preferences', [
            'preferences' => [[
                'notification_type' => $event->event_key,
                'email_enabled' => false,
                'in_app_enabled' => true,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'notification_type' => $event->event_key,
            'email_enabled' => false,
            'in_app_enabled' => true,
        ]);
        $this->getJson('/api/notification-preferences')
            ->assertOk()
            ->assertJsonFragment(['notification_type' => $event->event_key, 'email_enabled' => false]);
    }

    public function test_seeders_add_missing_defaults_without_overwriting_admin_edits(): void
    {
        $template = EmailTemplate::query()->where('name', 'approval_request')->firstOrFail();
        $event = NotificationEventSetting::query()->where('event_key', 'soi_step_changed')->firstOrFail();
        $template->update(['subject' => 'Administrator subject']);
        $event->update(['email_enabled' => false, 'template_name' => null]);

        $this->seed([EmailTemplateSeeder::class, NotificationEventSettingSeeder::class]);

        $this->assertSame('Administrator subject', $template->fresh()->subject);
        $this->assertFalse($event->fresh()->email_enabled);
        $this->assertNull($event->fresh()->template_name);
        $this->assertDatabaseHas('email_templates', ['name' => 'task_assigned']);
    }

    public function test_non_admin_cannot_access_management_apis(): void
    {
        Role::create(['id' => 1, 'name' => 'Superadmin', 'description' => 'System administrator', 'is_system_role' => true]);
        $role = Role::create(['name' => 'Project Officer', 'description' => 'Project Officer', 'is_system_role' => true]);
        $user = User::create([
            'username' => 'notification-user',
            'email' => 'notification-user@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Notification',
            'last_name' => 'User',
            'default_role_id' => $role->id,
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/notification-templates')->assertForbidden();
        $this->getJson('/api/notification-deliveries')->assertForbidden();
        $this->getJson('/api/notification-preferences')->assertOk();
    }

    private function admin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['id' => 1],
            ['name' => 'Superadmin', 'description' => 'System administrator', 'is_system_role' => true]
        );

        return User::create([
            'username' => 'notification-admin',
            'email' => 'notification-admin@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Notification',
            'last_name' => 'Admin',
            'default_role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
