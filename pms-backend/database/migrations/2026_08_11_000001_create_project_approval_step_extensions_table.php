<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_approval_step_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_approval_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_step_id')->constrained('approval_steps');
            $table->foreignId('extended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('extension_days');
            $table->dateTime('previous_due_at')->nullable();
            $table->dateTime('new_due_at');
            $table->text('reason');
            $table->timestamps();

            $table->index(['project_approval_id', 'approval_step_id'], 'approval_step_extensions_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_approval_step_extensions');
    }
};
