<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('submission_status', 40)->default('draft')->after('requires_approval');
            $table->timestamp('submitted_at')->nullable()->after('uploaded_at');
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('update_requested_at')->nullable()->after('submitted_by');
            $table->foreignId('update_requested_by')->nullable()->after('update_requested_at')->constrained('users')->nullOnDelete();
            $table->text('update_request_reason')->nullable()->after('update_requested_by');
            $table->index(['project_id', 'submission_status']);
        });

        DB::table('documents')
            ->where('is_deleted', false)
            ->update([
                'submission_status' => 'submitted',
                'submitted_at' => DB::raw('uploaded_at'),
                'submitted_by' => DB::raw('uploaded_by'),
            ]);
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'submission_status']);
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['update_requested_by']);
            $table->dropColumn([
                'submission_status',
                'submitted_at',
                'submitted_by',
                'update_requested_at',
                'update_requested_by',
                'update_request_reason',
            ]);
        });
    }
};
