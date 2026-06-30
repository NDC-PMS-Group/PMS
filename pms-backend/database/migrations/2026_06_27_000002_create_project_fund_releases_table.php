<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_fund_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained('project_requirements')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained('funding_sources')->nullOnDelete();
            $table->string('soi_section', 80)->nullable();
            $table->string('gate_step', 80)->nullable();
            $table->string('release_type', 60)->default('fund_release');
            $table->string('status', 40)->default('draft');
            $table->string('reference_no', 120)->nullable();
            $table->string('payee', 255)->nullable();
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('release_date')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'soi_section']);
            $table->index(['project_id', 'gate_step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_fund_releases');
    }
};
