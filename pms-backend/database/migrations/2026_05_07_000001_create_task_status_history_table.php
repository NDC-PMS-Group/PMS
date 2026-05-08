<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->integer('from_progress')->nullable();
            $table->integer('to_progress')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50)->default('status_changed');
            $table->text('notes')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['task_id', 'changed_at'], 'task_status_history_task_changed_idx');
            $table->index(['to_status', 'changed_at'], 'task_status_history_status_changed_idx');
        });

        $now = now();
        DB::table('tasks')
            ->where('is_deleted', false)
            ->orderBy('id')
            ->get()
            ->each(function ($task) use ($now) {
                DB::table('task_status_history')->insert([
                    'task_id' => $task->id,
                    'from_status' => null,
                    'to_status' => $task->status ?: 'pending',
                    'from_progress' => null,
                    'to_progress' => $task->progress_percentage ?? 0,
                    'changed_by' => $task->assigned_by,
                    'event_type' => 'created',
                    'notes' => 'Task created.',
                    'changed_at' => $task->created_at ?? $now,
                ]);

                if (($task->status ?? 'pending') !== 'pending') {
                    DB::table('task_status_history')->insert([
                        'task_id' => $task->id,
                        'from_status' => 'pending',
                        'to_status' => $task->status,
                        'from_progress' => 0,
                        'to_progress' => $task->progress_percentage ?? 0,
                        'changed_by' => $task->assigned_by,
                        'event_type' => 'status_changed',
                        'notes' => 'Backfilled current task status.',
                        'changed_at' => $task->updated_at ?? $task->created_at ?? $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_status_history');
    }
};
