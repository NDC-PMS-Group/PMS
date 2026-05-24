<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_requirements')) {
            return;
        }

        Schema::create('project_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('group_name', 120);
            $table->string('item_name', 255);
            $table->string('source_document', 120)->nullable();
            $table->string('track', 80)->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_applicable')->default(true);
            $table->boolean('svf_only')->default(false);
            $table->string('status', 50)->default('pending');
            $table->date('due_date')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'group_name']);
            $table->index(['project_id', 'status']);
            $table->unique(['project_id', 'group_name', 'item_name'], 'project_requirement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_requirements');
    }
};
