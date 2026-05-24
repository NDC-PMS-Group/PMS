<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'process_track')) {
                $table->string('process_track', 80)->default('bdg_investment')->after('description');
            }

            if (!Schema::hasColumn('projects', 'date_of_application')) {
                $table->date('date_of_application')->nullable()->after('process_track');
            }

            if (!Schema::hasColumn('projects', 'target_amount_to_raise')) {
                $table->decimal('target_amount_to_raise', 15, 2)->nullable()->after('actual_cost');
            }

            if (!Schema::hasColumn('projects', 'ndc_participation')) {
                $table->decimal('ndc_participation', 15, 2)->nullable()->after('target_amount_to_raise');
            }

            if (!Schema::hasColumn('projects', 'ndc_investment_criteria')) {
                $table->json('ndc_investment_criteria')->nullable()->after('ndc_participation');
            }

            if (!Schema::hasColumn('projects', 'project_rationale')) {
                $table->text('project_rationale')->nullable()->after('ndc_investment_criteria');
            }

            if (!Schema::hasColumn('projects', 'company_background')) {
                $table->text('company_background')->nullable()->after('project_rationale');
            }

            if (!Schema::hasColumn('projects', 'target_beneficiaries')) {
                $table->text('target_beneficiaries')->nullable()->after('company_background');
            }

            if (!Schema::hasColumn('projects', 'expected_benefits')) {
                $table->text('expected_benefits')->nullable()->after('target_beneficiaries');
            }

            if (!Schema::hasColumn('projects', 'risk_analysis')) {
                $table->text('risk_analysis')->nullable()->after('expected_benefits');
            }

            if (!Schema::hasColumn('projects', 'financial_metrics')) {
                $table->json('financial_metrics')->nullable()->after('risk_analysis');
            }

            if (!Schema::hasColumn('projects', 'implementation_milestones')) {
                $table->json('implementation_milestones')->nullable()->after('financial_metrics');
            }

            if (!Schema::hasColumn('projects', 'issues_problems')) {
                $table->text('issues_problems')->nullable()->after('implementation_milestones');
            }

            if (!Schema::hasColumn('projects', 'next_steps')) {
                $table->text('next_steps')->nullable()->after('issues_problems');
            }

            if (!Schema::hasColumn('projects', 'post_investment_strategy')) {
                $table->text('post_investment_strategy')->nullable()->after('next_steps');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $columns = [
                'process_track',
                'date_of_application',
                'target_amount_to_raise',
                'ndc_participation',
                'ndc_investment_criteria',
                'project_rationale',
                'company_background',
                'target_beneficiaries',
                'expected_benefits',
                'risk_analysis',
                'financial_metrics',
                'implementation_milestones',
                'issues_problems',
                'next_steps',
                'post_investment_strategy',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
