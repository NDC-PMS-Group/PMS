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
        Schema::create('activity_log_settings', function (Blueprint $table) {
            $table->id();
            
            // Retention settings
            $table->integer('retention_months')->default(3)->comment('Number of months to keep logs');
            
            // ID management
            $table->bigInteger('max_id')->default(1000000)->comment('Maximum ID before reuse');
            
            // Cleanup settings
            $table->boolean('auto_cleanup_enabled')->default(true)->comment('Enable automatic cleanup');
            $table->timestamp('last_cleanup_at')->nullable()->comment('Last time cleanup was run');
            
            $table->timestamps();
        });

        // Insert default settings
        DB::table('activity_log_settings')->insert([
            'retention_months' => 3,
            'max_id' => 1000000,
            'auto_cleanup_enabled' => true,
            'last_cleanup_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_log_settings');
    }
};
