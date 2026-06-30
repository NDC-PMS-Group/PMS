<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('monitoring_status', 30)->default('closed')->after('post_investment_strategy');
            $table->timestamp('monitoring_activated_at')->nullable()->after('monitoring_status');
            $table->foreignId('monitoring_activated_by')->nullable()->after('monitoring_activated_at')->constrained('users')->nullOnDelete();
            $table->date('monitoring_due_date')->nullable()->after('monitoring_activated_by');
            $table->text('monitoring_instructions')->nullable()->after('monitoring_due_date');
            $table->boolean('monitoring_proponent_access')->default(false)->after('monitoring_instructions');
            $table->timestamp('monitoring_closed_at')->nullable()->after('monitoring_proponent_access');
            $table->index(['monitoring_status', 'monitoring_due_date']);
        });

        Schema::create('notification_event_settings', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 80)->unique();
            $table->string('label', 150);
            $table->string('category', 80)->default('general');
            $table->text('description')->nullable();
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->string('template_name', 100)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['category', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_event_settings');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['monitoring_status', 'monitoring_due_date']);
            $table->dropForeign(['monitoring_activated_by']);
            $table->dropColumn([
                'monitoring_status',
                'monitoring_activated_at',
                'monitoring_activated_by',
                'monitoring_due_date',
                'monitoring_instructions',
                'monitoring_proponent_access',
                'monitoring_closed_at',
            ]);
        });
    }
};
