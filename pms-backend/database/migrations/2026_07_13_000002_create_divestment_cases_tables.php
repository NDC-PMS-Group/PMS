<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divestment_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('case_number', 40)->nullable()->unique();
            $table->string('phase', 40)->default('assessment');
            $table->string('status', 30)->default('active');
            $table->text('exit_strategy');
            $table->date('target_exit_date')->nullable();
            $table->decimal('estimated_proceeds', 15, 2)->nullable();
            $table->decimal('actual_proceeds', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('phase_started_at')->nullable();
            $table->timestamp('board_approved_at')->nullable();
            $table->timestamp('transfer_completed_at')->nullable();
            $table->timestamp('proceeds_collected_at')->nullable();
            $table->timestamp('closing_documents_completed_at')->nullable();
            $table->text('closure_notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'phase']);
            $table->index('target_exit_date');
        });

        Schema::create('divestment_case_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('divestment_case_id')->constrained()->cascadeOnDelete();
            $table->string('from_phase', 40)->nullable();
            $table->string('to_phase', 40);
            $table->text('notes');
            $table->foreignId('transitioned_by')->constrained('users');
            $table->timestamp('transitioned_at')->useCurrent();

            $table->index(['divestment_case_id', 'transitioned_at'], 'divestment_transition_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divestment_case_transitions');
        Schema::dropIfExists('divestment_cases');
    }
};
