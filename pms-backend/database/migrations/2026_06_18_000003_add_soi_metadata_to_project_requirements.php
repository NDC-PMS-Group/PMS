<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_requirements', function (Blueprint $table) {
            if (!Schema::hasColumn('project_requirements', 'owner_type')) {
                $table->string('owner_type', 30)->default('proponent')->after('track')->index();
            }

            if (!Schema::hasColumn('project_requirements', 'visibility')) {
                $table->string('visibility', 40)->default('proponent_visible')->after('owner_type')->index();
            }

            if (!Schema::hasColumn('project_requirements', 'soi_section')) {
                $table->string('soi_section', 80)->nullable()->after('visibility')->index();
            }

            if (!Schema::hasColumn('project_requirements', 'gate_step')) {
                $table->string('gate_step', 80)->nullable()->after('soi_section')->index();
            }
        });

        $derive = function (string $group, string $item): array {
            $text = strtolower($group . ' ' . $item);

            if ($group === '1. Intake Pack') {
                return [
                    'owner_type' => 'proponent',
                    'visibility' => 'proponent_visible',
                    'soi_section' => 'intake',
                    'gate_step' => null,
                ];
            }

            $isInternal = str_contains($text, 'response letter')
                || str_contains($text, 'investment committee')
                || str_contains($text, 'mancom')
                || str_contains($text, 'management committee')
                || str_contains($text, 'board')
                || str_contains($text, 'resolution')
                || str_contains($text, 'secretary certificate')
                || str_contains($text, 'secretary\'s certificate')
                || str_contains($text, 'neda')
                || str_contains($text, 'icc')
                || str_contains($text, 'jv selection')
                || str_contains($text, 'notice of award')
                || str_contains($text, 'noa')
                || str_contains($text, 'jva')
                || str_contains($text, 'agreement')
                || str_contains($text, 'contract')
                || str_contains($text, 'fund release')
                || str_contains($text, 'fund deployment')
                || str_contains($text, 'receipt')
                || str_contains($text, 'monitoring evidence')
                || str_contains($text, 'milestone')
                || str_contains($text, 'divestment')
                || str_contains($text, 'transfer')
                || str_contains($text, 'construction')
                || str_contains($text, 'ded ')
                || str_contains($text, 'procurement')
                || str_contains($text, 'materials requisition')
                || str_contains($text, 'bidding')
                || str_contains($text, 'turn-over')
                || str_contains($text, 'turnover');

            $section = match (true) {
                str_contains($text, 'divest') || str_contains($text, 'transfer') => 'divestment',
                str_contains($text, 'monitor') || str_contains($text, 'milestone') || str_contains($text, 'coa') => 'implementation_monitoring',
                str_contains($text, 'post-investment') || str_contains($text, 'post investment') => 'post_investment_strategy',
                str_contains($text, 'agreement') || str_contains($text, 'contract') || str_contains($text, 'fund') || str_contains($text, 'receipt') || str_contains($text, 'jva') || str_contains($text, 'construction') || str_contains($text, 'ded ') => 'agreement_fund_release',
                str_contains($text, 'board') || str_contains($text, 'resolution') || str_contains($text, 'secretary certificate') || str_contains($text, 'secretary\'s certificate') => 'board_approval',
                str_contains($text, 'mancom') || str_contains($text, 'management committee') || str_contains($text, 'workgroup') || str_contains($text, 'recommendation') || str_contains($text, 'presentation') => 'management_review',
                str_contains($text, 'neda') || str_contains($text, 'icc') || str_contains($text, 'due diligence') || str_contains($text, 'evaluation') || str_contains($text, 'study') || str_contains($text, 'financial model') || str_contains($text, 'risk') => 'due_diligence',
                str_contains($text, 'requirement') || str_contains($text, 'checklist') || str_contains($text, 'response letter') || str_contains($text, 'legal') || str_contains($text, 'financial') || str_contains($text, 'sec ') || str_contains($text, 'dti') => 'requirements',
                default => 'intake',
            };

            $gate = match (true) {
                str_contains($text, 'divest') || str_contains($text, 'transfer') => 'divestment',
                str_contains($text, 'neda') || str_contains($text, 'icc') || str_contains($text, 'jv selection') || str_contains($text, 'notice of award') || str_contains($text, 'noa') => 'jv',
                str_contains($text, 'agreement') || str_contains($text, 'contract') || str_contains($text, 'fund') || str_contains($text, 'receipt') || str_contains($text, 'jva') || str_contains($text, 'construction') || str_contains($text, 'ded ') => 'fund_release',
                str_contains($text, 'board') || str_contains($text, 'resolution') || str_contains($text, 'secretary certificate') || str_contains($text, 'secretary\'s certificate') => 'board',
                str_contains($text, 'mancom') || str_contains($text, 'management committee') || str_contains($text, 'recommendation') || str_contains($text, 'presentation') => 'mancom',
                str_contains($text, 'monitor') || str_contains($text, 'milestone') || str_contains($text, 'coa') => 'monitoring',
                default => null,
            };

            return [
                'owner_type' => $isInternal ? 'internal' : 'proponent',
                'visibility' => $isInternal ? 'internal_only' : 'proponent_visible',
                'soi_section' => $section,
                'gate_step' => $isInternal ? $gate : null,
            ];
        };

        DB::table('project_requirements')
            ->select(['id', 'group_name', 'item_name'])
            ->orderBy('id')
            ->chunkById(200, function ($requirements) use ($derive) {
                foreach ($requirements as $requirement) {
                    DB::table('project_requirements')
                        ->where('id', $requirement->id)
                        ->update($derive((string) $requirement->group_name, (string) $requirement->item_name));
                }
            });
    }

    public function down(): void
    {
        Schema::table('project_requirements', function (Blueprint $table) {
            if (Schema::hasColumn('project_requirements', 'gate_step')) {
                $table->dropColumn('gate_step');
            }
            if (Schema::hasColumn('project_requirements', 'soi_section')) {
                $table->dropColumn('soi_section');
            }
            if (Schema::hasColumn('project_requirements', 'visibility')) {
                $table->dropColumn('visibility');
            }
            if (Schema::hasColumn('project_requirements', 'owner_type')) {
                $table->dropColumn('owner_type');
            }
        });
    }
};
