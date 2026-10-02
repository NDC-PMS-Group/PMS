<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('project_monitoring_reports', 'compliance_type')) {
            Schema::table('project_monitoring_reports', function (Blueprint $table) {
                $table->string('compliance_type', 30)->default('progress')->after('quarter');
                $table->unsignedInteger('jobs_generated_male')->nullable()->after('jobs_generated');
                $table->unsignedInteger('jobs_generated_female')->nullable()->after('jobs_generated_male');
                $table->unsignedInteger('jobs_retained_male')->nullable()->after('jobs_retained');
                $table->unsignedInteger('jobs_retained_female')->nullable()->after('jobs_retained_male');
            });
        }

        if (! Schema::hasIndex('project_monitoring_reports', 'monitoring_project_fk_support_index')) {
            Schema::table('project_monitoring_reports', function (Blueprint $table) {
                $table->index('project_id', 'monitoring_project_fk_support_index');
            });
        }

        if (Schema::hasIndex('project_monitoring_reports', 'monitoring_project_period_unique')) {
            Schema::table('project_monitoring_reports', function (Blueprint $table) {
                $table->dropUnique('monitoring_project_period_unique');
            });
        }

        $existingReports = DB::table('project_monitoring_reports')->where('compliance_type', 'progress')->orderBy('id')->get();
        foreach ($existingReports as $existingReport) {
            $copy = (array) $existingReport;
            unset($copy['id']);

            $exists = fn (string $type): bool => DB::table('project_monitoring_reports')
                ->where('project_id', $existingReport->project_id)
                ->where('reporting_year', $existingReport->reporting_year)
                ->where('quarter', $existingReport->quarter)
                ->where('compliance_type', $type)
                ->exists();

            if (! $exists('employment')) {
                DB::table('project_monitoring_reports')->insert(array_merge($copy, [
                    'compliance_type' => 'employment',
                    'milestones' => null,
                    'impact' => null,
                    'monitoring_narrative' => null,
                    'revenue' => 0,
                    'remittance' => 0,
                ]));
            }

            if (! $exists('financial')) {
                DB::table('project_monitoring_reports')->insert(array_merge($copy, [
                    'compliance_type' => 'financial',
                    'jobs_generated' => 0,
                    'jobs_retained' => 0,
                    'milestones' => null,
                    'impact' => null,
                    'monitoring_narrative' => null,
                ]));
            }
        }

        if (! Schema::hasIndex('project_monitoring_reports', 'monitoring_project_period_type_unique')) {
            Schema::table('project_monitoring_reports', function (Blueprint $table) {
                $table->unique(
                    ['project_id', 'reporting_year', 'quarter', 'compliance_type'],
                    'monitoring_project_period_type_unique'
                );
                $table->index(['compliance_type', 'status'], 'monitoring_type_status_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('project_monitoring_reports', function (Blueprint $table) {
            $table->dropUnique('monitoring_project_period_type_unique');
            $table->dropIndex('monitoring_type_status_index');
        });

        DB::table('project_monitoring_reports')->whereIn('compliance_type', ['employment', 'financial'])->delete();

        Schema::table('project_monitoring_reports', function (Blueprint $table) {
            $table->dropColumn([
                'compliance_type',
                'jobs_generated_male',
                'jobs_generated_female',
                'jobs_retained_male',
                'jobs_retained_female',
            ]);
            $table->unique(['project_id', 'reporting_year', 'quarter'], 'monitoring_project_period_unique');
        });

        if (Schema::hasIndex('project_monitoring_reports', 'monitoring_project_fk_support_index')) {
            Schema::table('project_monitoring_reports', function (Blueprint $table) {
                $table->dropIndex('monitoring_project_fk_support_index');
            });
        }
    }
};
