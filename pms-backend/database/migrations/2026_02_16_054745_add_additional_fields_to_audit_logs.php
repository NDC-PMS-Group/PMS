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
        Schema::table('audit_logs', function (Blueprint $table) {
            // User information at time of action
            $table->string('email')->after('user_id')->index();
            
            // Human-readable description
            $table->text('description')->nullable()->after('action');
            
            // Parsed user agent information
            $table->string('device_type', 50)->nullable()->after('user_agent'); // Desktop, Mobile, Tablet
            $table->string('browser', 50)->nullable()->after('device_type'); // Chrome, Firefox, Safari, etc.
            $table->string('browser_version', 20)->nullable()->after('browser');
            $table->string('platform', 50)->nullable()->after('browser_version'); // Windows, macOS, Linux, iOS, Android
            $table->string('platform_version', 20)->nullable()->after('platform');
            
            // Add indexes for better filtering and searching
            $table->index('email');
            $table->index('device_type');
            $table->index('browser');
            $table->index('platform');
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
