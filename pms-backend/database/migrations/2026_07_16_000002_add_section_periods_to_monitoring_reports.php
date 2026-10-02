<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_monitoring_reports', function (Blueprint $table) {
            $table->date('employment_period_start')->nullable()->after('period_end');
            $table->date('employment_period_end')->nullable()->after('employment_period_start');
            $table->date('financial_period_start')->nullable()->after('employment_period_end');
            $table->date('financial_period_end')->nullable()->after('financial_period_start');
            $table->date('narrative_period_start')->nullable()->after('financial_period_end');
            $table->date('narrative_period_end')->nullable()->after('narrative_period_start');
        });

        DB::table('project_monitoring_reports')->update([
            'employment_period_start' => DB::raw('period_start'),
            'employment_period_end' => DB::raw('period_end'),
            'financial_period_start' => DB::raw('period_start'),
            'financial_period_end' => DB::raw('period_end'),
            'narrative_period_start' => DB::raw('period_start'),
            'narrative_period_end' => DB::raw('period_end'),
        ]);
    }

    public function down(): void
    {
        Schema::table('project_monitoring_reports', function (Blueprint $table) {
            $table->dropColumn([
                'employment_period_start',
                'employment_period_end',
                'financial_period_start',
                'financial_period_end',
                'narrative_period_start',
                'narrative_period_end',
            ]);
        });
    }
};
