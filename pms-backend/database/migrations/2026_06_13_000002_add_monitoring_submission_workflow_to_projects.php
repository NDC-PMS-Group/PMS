<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('monitoring_submission_status', 30)->default('not_requested')->after('monitoring_status');
            $table->timestamp('monitoring_draft_saved_at')->nullable()->after('monitoring_submission_status');
            $table->timestamp('monitoring_submitted_at')->nullable()->after('monitoring_draft_saved_at');
            $table->foreignId('monitoring_submitted_by')->nullable()->after('monitoring_submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('monitoring_reviewed_at')->nullable()->after('monitoring_submitted_by');
            $table->foreignId('monitoring_reviewed_by')->nullable()->after('monitoring_reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('monitoring_review_notes')->nullable()->after('monitoring_reviewed_by');
            $table->index(['monitoring_submission_status', 'monitoring_due_date'], 'projects_monitoring_submission_due_index');
        });

        DB::table('projects')
            ->where('monitoring_status', 'active')
            ->where('monitoring_submission_status', 'not_requested')
            ->update(['monitoring_submission_status' => 'draft']);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_monitoring_submission_due_index');
            $table->dropForeign(['monitoring_submitted_by']);
            $table->dropForeign(['monitoring_reviewed_by']);
            $table->dropColumn([
                'monitoring_submission_status',
                'monitoring_draft_saved_at',
                'monitoring_submitted_at',
                'monitoring_submitted_by',
                'monitoring_reviewed_at',
                'monitoring_reviewed_by',
                'monitoring_review_notes',
            ]);
        });
    }
};
