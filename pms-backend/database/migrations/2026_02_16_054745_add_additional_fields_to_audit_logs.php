<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // User information at time of action
            if (!Schema::hasColumn('audit_logs', 'email')) {
                $table->string('email')->after('user_id');
            }
            
            // Human-readable description
            if (!Schema::hasColumn('audit_logs', 'description')) {
                $table->text('description')->nullable()->after('action');
            }
            
            // Parsed user agent information
            if (!Schema::hasColumn('audit_logs', 'device_type')) {
                $table->string('device_type', 50)->nullable()->after('user_agent'); // Desktop, Mobile, Tablet
            }
            if (!Schema::hasColumn('audit_logs', 'browser')) {
                $table->string('browser', 50)->nullable()->after('device_type'); // Chrome, Firefox, Safari, etc.
            }
            if (!Schema::hasColumn('audit_logs', 'browser_version')) {
                $table->string('browser_version', 20)->nullable()->after('browser');
            }
            if (!Schema::hasColumn('audit_logs', 'platform')) {
                $table->string('platform', 50)->nullable()->after('browser_version'); // Windows, macOS, Linux, iOS, Android
            }
            if (!Schema::hasColumn('audit_logs', 'platform_version')) {
                $table->string('platform_version', 20)->nullable()->after('platform');
            }
        });

        // Add indexes for better filtering and searching; avoid duplicate-index failures.
        $existingIndexes = collect(DB::select('SHOW INDEX FROM audit_logs'))
            ->pluck('Key_name')
            ->all();

        Schema::table('audit_logs', function (Blueprint $table) use ($existingIndexes) {
            if (!in_array('audit_logs_email_index', $existingIndexes, true)) {
                $table->index('email');
            }
            if (!in_array('audit_logs_device_type_index', $existingIndexes, true)) {
                $table->index('device_type');
            }
            if (!in_array('audit_logs_browser_index', $existingIndexes, true)) {
                $table->index('browser');
            }
            if (!in_array('audit_logs_platform_index', $existingIndexes, true)) {
                $table->index('platform');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $existingIndexes = collect(DB::select('SHOW INDEX FROM audit_logs'))
            ->pluck('Key_name')
            ->all();

        Schema::table('audit_logs', function (Blueprint $table) {
            // Keep closure for consistent schema operations.
        });

        Schema::table('audit_logs', function (Blueprint $table) use ($existingIndexes) {
            if (in_array('audit_logs_email_index', $existingIndexes, true)) {
                $table->dropIndex('audit_logs_email_index');
            }
            if (in_array('audit_logs_device_type_index', $existingIndexes, true)) {
                $table->dropIndex('audit_logs_device_type_index');
            }
            if (in_array('audit_logs_browser_index', $existingIndexes, true)) {
                $table->dropIndex('audit_logs_browser_index');
            }
            if (in_array('audit_logs_platform_index', $existingIndexes, true)) {
                $table->dropIndex('audit_logs_platform_index');
            }

            if (Schema::hasColumn('audit_logs', 'platform_version')) {
                $table->dropColumn('platform_version');
            }
            if (Schema::hasColumn('audit_logs', 'platform')) {
                $table->dropColumn('platform');
            }
            if (Schema::hasColumn('audit_logs', 'browser_version')) {
                $table->dropColumn('browser_version');
            }
            if (Schema::hasColumn('audit_logs', 'browser')) {
                $table->dropColumn('browser');
            }
            if (Schema::hasColumn('audit_logs', 'device_type')) {
                $table->dropColumn('device_type');
            }
            if (Schema::hasColumn('audit_logs', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('audit_logs', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
