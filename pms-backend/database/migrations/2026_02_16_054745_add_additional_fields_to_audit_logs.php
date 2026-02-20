<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasEmail = Schema::hasColumn('audit_logs', 'email');
        $hasDescription = Schema::hasColumn('audit_logs', 'description');
        $hasDeviceType = Schema::hasColumn('audit_logs', 'device_type');
        $hasBrowser = Schema::hasColumn('audit_logs', 'browser');
        $hasBrowserVersion = Schema::hasColumn('audit_logs', 'browser_version');
        $hasPlatform = Schema::hasColumn('audit_logs', 'platform');
        $hasPlatformVersion = Schema::hasColumn('audit_logs', 'platform_version');

        if (
            $hasEmail &&
            $hasDescription &&
            $hasDeviceType &&
            $hasBrowser &&
            $hasBrowserVersion &&
            $hasPlatform &&
            $hasPlatformVersion
        ) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) use (
            $hasEmail,
            $hasDescription,
            $hasDeviceType,
            $hasBrowser,
            $hasBrowserVersion,
            $hasPlatform,
            $hasPlatformVersion
        ) {
            if (!$hasEmail) {
                $table->string('email')->after('user_id')->index();
            }
            if (!$hasDescription) {
                $table->text('description')->nullable()->after('action');
            }
            if (!$hasDeviceType) {
                $table->string('device_type', 50)->nullable()->after('user_agent')->index();
            }
            if (!$hasBrowser) {
                $table->string('browser', 50)->nullable()->after('device_type')->index();
            }
            if (!$hasBrowserVersion) {
                $table->string('browser_version', 20)->nullable()->after('browser');
            }
            if (!$hasPlatform) {
                $table->string('platform', 50)->nullable()->after('browser_version')->index();
            }
            if (!$hasPlatformVersion) {
                $table->string('platform_version', 20)->nullable()->after('platform');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['email']);
            $table->dropIndex(['device_type']);
            $table->dropIndex(['browser']);
            $table->dropIndex(['platform']);
            
            // Drop columns
            $table->dropColumn([
                'email',
                'description',
                'device_type',
                'browser',
                'browser_version',
                'platform',
                'platform_version',
            ]);
        });
    }
};
