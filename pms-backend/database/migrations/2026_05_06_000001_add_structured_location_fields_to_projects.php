<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'location_region_code')) {
                $table->string('location_region_code', 20)->nullable()->after('location_address');
            }
            if (!Schema::hasColumn('projects', 'location_region_name')) {
                $table->string('location_region_name')->nullable()->after('location_region_code');
            }
            if (!Schema::hasColumn('projects', 'location_province_code')) {
                $table->string('location_province_code', 20)->nullable()->after('location_region_name');
            }
            if (!Schema::hasColumn('projects', 'location_province_name')) {
                $table->string('location_province_name')->nullable()->after('location_province_code');
            }
            if (!Schema::hasColumn('projects', 'location_city_code')) {
                $table->string('location_city_code', 20)->nullable()->after('location_province_name');
            }
            if (!Schema::hasColumn('projects', 'location_city_name')) {
                $table->string('location_city_name')->nullable()->after('location_city_code');
            }
            if (!Schema::hasColumn('projects', 'location_barangay_code')) {
                $table->string('location_barangay_code', 20)->nullable()->after('location_city_name');
            }
            if (!Schema::hasColumn('projects', 'location_barangay_name')) {
                $table->string('location_barangay_name')->nullable()->after('location_barangay_code');
            }
            if (!Schema::hasColumn('projects', 'location_street')) {
                $table->string('location_street')->nullable()->after('location_barangay_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $columns = [
                'location_region_code',
                'location_region_name',
                'location_province_code',
                'location_province_name',
                'location_city_code',
                'location_city_name',
                'location_barangay_code',
                'location_barangay_name',
                'location_street',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
