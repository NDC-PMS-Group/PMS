<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proponent_registration_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 80);
            $table->string('title', 255);
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_type', 255)->nullable();
            $table->string('review_status', 40)->default('pending');
            $table->text('review_remarks')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'document_type']);
            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proponent_registration_documents');
    }
};
