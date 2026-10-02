<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_deadline_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->date('due_date');
            $table->string('reminder_key', 50);
            $table->string('event_type', 80);
            $table->integer('days_remaining');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['task_id', 'due_date', 'reminder_key'], 'task_deadline_reminder_unique');
            $table->index(['event_type', 'sent_at'], 'task_deadline_event_sent_idx');
        });

        $templateVariables = [
            'user_name',
            'project_code',
            'project_title',
            'task_title',
            'due_date',
            'timing',
            'action_url',
            'action_label',
        ];
        $templateSubject = '{{timing}}: {{task_title}}';
        $templateBody = "Hello {{user_name}},\n\n{{timing}} for the following SOI work-plan task.\n\nProject: {{project_code}} - {{project_title}}\nTask: {{task_title}}\nDue Date: {{due_date}}\nStatus: {{timing}}\n\nPlease open the project work plan and take the required action.\n\nBest regards,\nNDC Project Management System";

        DB::table('email_templates')->insertOrIgnore([
            'name' => 'task_deadline_reminder',
            'subject' => $templateSubject,
            'body' => $templateBody,
            'variables' => json_encode($templateVariables),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateId = DB::table('email_templates')->where('name', 'task_deadline_reminder')->value('id');
        DB::table('notification_template_versions')->insertOrIgnore([
            'email_template_id' => $templateId,
            'version' => 1,
            'status' => 'published',
            'subject' => $templateSubject,
            'body' => $templateBody,
            'variables' => json_encode($templateVariables),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $events = [
            ['task_deadline_due_soon', 'Task deadline approaching', 'Alerts responsible users and administrators three days and one day before a task deadline.'],
            ['task_deadline_reached', 'Task deadline reached', 'Alerts responsible users and administrators when a task reaches its deadline.'],
            ['task_deadline_overdue', 'Task deadline overdue', 'Alerts responsible users and administrators one day overdue and weekly until resolved.'],
        ];

        foreach ($events as [$key, $label, $description]) {
            $values = [
                'label' => $label,
                'category' => 'Work plan',
                'description' => $description,
                'in_app_enabled' => true,
                'email_enabled' => true,
                'template_name' => 'task_deadline_reminder',
                'updated_at' => now(),
                'created_at' => now(),
            ];

            if (Schema::hasColumn('notification_event_settings', 'email_template_id')) {
                $values['email_template_id'] = $templateId;
            }

            DB::table('notification_event_settings')->updateOrInsert(['event_key' => $key], $values);
        }
    }

    public function down(): void
    {
        DB::table('notification_event_settings')
            ->whereIn('event_key', ['task_deadline_due_soon', 'task_deadline_overdue'])
            ->delete();

        $reachedEventValues = [
            'template_name' => 'deadline_reminder',
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('notification_event_settings', 'email_template_id')) {
            $reachedEventValues['email_template_id'] = DB::table('email_templates')
                ->where('name', 'deadline_reminder')
                ->value('id');
        }

        DB::table('notification_event_settings')
            ->where('event_key', 'task_deadline_reached')
            ->update($reachedEventValues);

        DB::table('email_templates')->where('name', 'task_deadline_reminder')->delete();
        Schema::dropIfExists('task_deadline_notifications');
    }
};
