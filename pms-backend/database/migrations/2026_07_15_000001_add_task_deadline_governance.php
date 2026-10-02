<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('workflow_sort_order')->nullable()->after('template_source');
            $table->date('deadline_alerted_for')->nullable()->after('due_date');
            $table->timestamp('deadline_alerted_at')->nullable()->after('deadline_alerted_for');
            $table->index(['deadline_alerted_for', 'status'], 'tasks_deadline_alert_status_idx');
        });

        Schema::table('task_status_history', function (Blueprint $table) {
            $table->date('previous_due_date')->nullable()->after('to_progress');
            $table->date('new_due_date')->nullable()->after('previous_due_date');
            $table->date('actual_completion_date')->nullable()->after('new_due_date');
            $table->text('reason')->nullable()->after('actual_completion_date');
            $table->index(['event_type', 'changed_at'], 'task_history_event_changed_idx');
        });

        DB::table('default_tasks')->orderBy('id')->get()->each(function ($template) {
            DB::table('tasks')
                ->where('template_source', 'like', 'soi:%:' . $template->id)
                ->update(['workflow_sort_order' => $template->sort_order]);
        });

        DB::table('notification_event_settings')->updateOrInsert(
            ['event_key' => 'task_deadline_reached'],
            [
                'label' => 'Task deadline reached',
                'category' => 'tasks',
                'description' => 'Alerts administrators when an incomplete SOI work-plan task reaches its deadline.',
                'in_app_enabled' => true,
                'email_enabled' => true,
                'template_name' => 'deadline_reminder',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('notification_event_settings')
            ->where('event_key', 'task_deadline_reached')
            ->delete();

        Schema::table('task_status_history', function (Blueprint $table) {
            $table->dropIndex('task_history_event_changed_idx');
            $table->dropColumn([
                'previous_due_date',
                'new_due_date',
                'actual_completion_date',
                'reason',
            ]);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_deadline_alert_status_idx');
            $table->dropColumn(['workflow_sort_order', 'deadline_alerted_for', 'deadline_alerted_at']);
        });
    }
};
