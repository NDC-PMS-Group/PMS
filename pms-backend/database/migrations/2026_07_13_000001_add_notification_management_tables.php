<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_event_settings', function (Blueprint $table) {
            $table->foreignId('email_template_id')->nullable()->after('template_name')->constrained('email_templates')->nullOnDelete();
        });

        DB::table('notification_event_settings')->whereNotNull('template_name')->orderBy('id')->eachById(function ($setting) {
            DB::table('notification_event_settings')->where('id', $setting->id)->update([
                'email_template_id' => DB::table('email_templates')->where('name', $setting->template_name)->value('id'),
            ]);
        });

        Schema::create('notification_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft');
            $table->string('subject');
            $table->text('body');
            $table->json('variables')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('restored_from_id')->nullable()->constrained('notification_template_versions')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['email_template_id', 'version'], 'notification_template_version_unique');
            $table->index(['email_template_id', 'status', 'version'], 'notification_template_status_idx');
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('template_version_id')->nullable()->constrained('notification_template_versions')->nullOnDelete();
            $table->string('event_key', 80)->nullable();
            $table->string('channel', 20)->default('email');
            $table->text('recipient_address');
            $table->string('subject');
            $table->longText('payload')->nullable();
            $table->string('status', 20)->default('queued');
            $table->boolean('is_test')->default(false);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('failure_reason')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'notification_delivery_status_idx');
            $table->index(['event_key', 'created_at'], 'notification_delivery_event_idx');
        });

        $templates = DB::table('email_templates')->orderBy('id')->get();
        foreach ($templates as $template) {
            DB::table('notification_template_versions')->insert([
                'email_template_id' => $template->id,
                'version' => 1,
                'status' => 'published',
                'subject' => $template->subject,
                'body' => $template->body,
                'variables' => $template->variables,
                'published_at' => $template->updated_at ?? now(),
                'created_at' => $template->created_at ?? now(),
                'updated_at' => $template->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_template_versions');
        Schema::table('notification_event_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('email_template_id');
        });
    }
};
