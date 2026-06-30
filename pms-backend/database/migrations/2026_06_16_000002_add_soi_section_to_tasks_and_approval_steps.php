<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('soi_section', 80)->nullable()->after('task_type')->index();
        });

        Schema::table('approval_steps', function (Blueprint $table) {
            $table->string('soi_section', 80)->nullable()->after('step_name')->index();
        });

        $deriveTaskSection = function (?string $taskType, ?string $title): ?string {
            $type = strtolower((string) $taskType);
            $text = strtolower(trim($type . ' ' . (string) $title));

            if (str_contains($text, 'divest')) return 'divestment';
            if (str_contains($text, 'post-investment') || str_contains($text, 'post investment')) return 'post_investment_strategy';
            if (str_contains($text, 'monitor')) return 'implementation_monitoring';
            if (str_contains($text, 'fund') || str_contains($text, 'agreement') || str_contains($text, 'jva') || str_contains($text, 'construction')) return 'agreement_fund_release';
            if (str_contains($text, 'board')) return 'board_approval';
            if (str_contains($text, 'mancom') || str_contains($text, 'workgroup') || $type === 'approval') return 'management_review';
            if (str_contains($text, 'diligence') || str_contains($text, 'evaluation') || str_contains($text, 'study') || $type === 'due_diligence') return 'due_diligence';
            if (str_contains($text, 'requirement') || str_contains($text, 'completeness')) return 'requirements';
            if (str_contains($text, 'completion') || str_contains($text, 'turn-over') || str_contains($text, 'turnover')) return 'completion';
            if (str_contains($text, 'intake') || str_contains($text, 'concept') || str_contains($text, 'submission') || $type === 'intake') return 'intake';

            return $type ?: null;
        };

        $deriveStepSection = function (?string $stepName): ?string {
            $name = strtolower((string) $stepName);

            if (str_contains($name, 'divest')) return 'divestment';
            if (str_contains($name, 'post-investment') || str_contains($name, 'post investment')) return 'post_investment_strategy';
            if (str_contains($name, 'monitor') || str_contains($name, 'milestone')) return 'implementation_monitoring';
            if (str_contains($name, 'agreement') || str_contains($name, 'fund release') || str_contains($name, 'jva') || str_contains($name, 'construction')) return 'agreement_fund_release';
            if (str_contains($name, 'board')) return 'board_approval';
            if (str_contains($name, 'mancom') || str_contains($name, 'workgroup') || str_contains($name, 'agm')) return 'management_review';
            if (str_contains($name, 'due diligence') || str_contains($name, 'evaluation') || str_contains($name, 'validation') || str_contains($name, 'study')) return 'due_diligence';
            if (str_contains($name, 'requirement') || str_contains($name, 'completeness') || str_contains($name, 'checklist') || str_contains($name, 'response letter')) return 'requirements';
            if (str_contains($name, 'completion') || str_contains($name, 'turn-over') || str_contains($name, 'turnover')) return 'completion';
            if (str_contains($name, 'submission') || str_contains($name, 'intake') || str_contains($name, 'concept') || str_contains($name, 'kyc') || str_contains($name, 'loi')) return 'intake';

            return null;
        };

        DB::table('tasks')
            ->select(['id', 'task_type', 'title'])
            ->orderBy('id')
            ->each(function ($task) use ($deriveTaskSection) {
                DB::table('tasks')
                    ->where('id', $task->id)
                    ->update(['soi_section' => $deriveTaskSection($task->task_type, $task->title)]);
            });

        DB::table('approval_steps')
            ->select(['id', 'step_name'])
            ->orderBy('id')
            ->each(function ($step) use ($deriveStepSection) {
                DB::table('approval_steps')
                    ->where('id', $step->id)
                    ->update(['soi_section' => $deriveStepSection($step->step_name)]);
            });
    }

    public function down(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropColumn('soi_section');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('soi_section');
        });
    }
};
