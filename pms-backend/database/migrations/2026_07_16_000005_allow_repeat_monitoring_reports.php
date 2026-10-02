<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('project_monitoring_reports', 'monitoring_project_period_type_unique')) {
            Schema::table('project_monitoring_reports', function (Blueprint $table) {
                $table->dropUnique('monitoring_project_period_type_unique');
            });
        }

        if (! Schema::hasIndex('project_monitoring_reports', 'monitoring_project_period_type_index')) {
            Schema::table('project_monitoring_reports', function (Blueprint $table) {
                $table->index(
                    ['project_id', 'reporting_year', 'quarter', 'compliance_type'],
                    'monitoring_project_period_type_index'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('project_monitoring_reports', 'monitoring_project_period_type_index')) {
            Schema::table('project_monitoring_reports', function (Blueprint $table) {
                $table->dropIndex('monitoring_project_period_type_index');
            });
        }
    }
};
