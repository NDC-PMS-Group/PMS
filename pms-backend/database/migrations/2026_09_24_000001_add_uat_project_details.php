<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('record_type', 20)->nullable()->index();
            $table->string('project_type_other')->nullable();
            $table->string('industry_other')->nullable();
            $table->string('sector_other')->nullable();
            $table->date('operations_start_date')->nullable();
            $table->json('other_financing')->nullable();
            $table->json('narrative_content')->nullable();
        });

        Schema::create('project_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('region_code', 20)->index();
            $table->string('region_name');
            $table->string('province_code', 20)->nullable()->index();
            $table->string('province_name')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        foreach (['project_types', 'industries', 'sectors'] as $table) {
            DB::table($table)->insertOrIgnore([
                'name' => 'Others', 'description' => 'Specify another classification', 'created_at' => now(),
            ]);
        }
        DB::table('project_types')->insertOrIgnore([
            'name' => 'Government-to-Government', 'description' => 'Government-to-government initiatives', 'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_locations');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['record_type', 'project_type_other', 'industry_other', 'sector_other', 'operations_start_date', 'other_financing', 'narrative_content']);
        });
        // Lookup entries may be referenced by records; retain them on rollback.
    }
};
