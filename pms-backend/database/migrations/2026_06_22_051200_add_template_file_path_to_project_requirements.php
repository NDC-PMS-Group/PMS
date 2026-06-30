<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_requirements', function (Blueprint $table) {
            if (!Schema::hasColumn('project_requirements', 'template_file_path')) {
                $table->string('template_file_path', 255)->nullable()->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_requirements', function (Blueprint $table) {
            if (Schema::hasColumn('project_requirements', 'template_file_path')) {
                $table->dropColumn('template_file_path');
            }
        });
    }
};
