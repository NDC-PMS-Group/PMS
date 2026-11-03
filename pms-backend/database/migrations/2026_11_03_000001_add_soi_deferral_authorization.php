<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->date('board_presentation_date')->nullable());
        Schema::table('project_requirements', function (Blueprint $table) {
            $table->foreignId('deferral_authorized_by')->nullable()->constrained('users');
            $table->timestamp('deferral_authorized_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deferral_authorized_by');
            $table->dropColumn('deferral_authorized_at');
        });
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('board_presentation_date'));
    }
};
