<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->string('record_type_other')->nullable());
        Schema::table('project_locations', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_locations', fn (Blueprint $table) => $table->dropColumn(['latitude', 'longitude']));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('record_type_other'));
    }
};
