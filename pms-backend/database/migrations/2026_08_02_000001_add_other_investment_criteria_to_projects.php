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
            if (!Schema::hasColumn('projects', 'ndc_investment_criteria_other')) {
                $table->string('ndc_investment_criteria_other')->nullable()->after('ndc_investment_criteria');
            }
        });

        if (Schema::hasTable('investment_criteria')) {
            DB::table('investment_criteria')->updateOrInsert(
                ['key' => 'others'],
                [
                    'name' => 'Others',
                    'description' => 'Other NDC investment criterion defined by the project team.',
                    'sort_order' => 90,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'ndc_investment_criteria_other')) {
                $table->dropColumn('ndc_investment_criteria_other');
            }
        });
    }
};
