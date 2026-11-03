<?php

namespace App\Support;

use App\Models\ApprovalStep;
use App\Models\Project;

class SoiDevelopmentProcedure
{
    public const SOURCE = 'SPG Project Development SOI 2026 Rev. 05';

    public static function applies(Project $project): bool
    {
        return !$project->is_svf && in_array($project->origin_track ?: $project->process_track, [
            'bdg_investment', 'spg_traditional', 'spg_ndc_own', 'spg_jv',
        ], true);
    }

    public static function initialSections(Project $project): array
    {
        return self::applies($project) ? ['intake'] : ['intake', 'requirements'];
    }

    public static function alignRequirements(Project $project): void
    {
        if (self::applies($project) && ($project->origin_track ?: $project->process_track) === 'spg_jv') {
            $project->requirements()->firstOrCreate(['item_name' => 'Applicable DEPDev JV guideline/version and compliance checklist'], [
                'group_name' => '4. DEPDev-ICC Process', 'source_document' => self::SOURCE.' section 9',
                'track' => 'spg_jv', 'owner_type' => 'internal', 'visibility' => 'internal_only',
                'soi_section' => 'board_approval', 'gate_step' => 'spg_jv_neda_icc',
                'is_required' => true, 'is_applicable' => true, 'status' => 'pending', 'sort_order' => 400,
            ]);
        }
        if (!self::applies($project) || !in_array($project->origin_track ?: $project->process_track, ['bdg_investment', 'spg_traditional'], true)) {
            return;
        }
        $changes = [
            'Brochure, pitch deck, or company profile' => ['item_name' => 'Project concept / pitch deck', 'soi_section' => 'intake', 'source_document' => self::SOURCE.' section 7.1'],
            'Letter of Intent or project concept' => ['item_name' => 'Letter of Intent (LOI)', 'soi_section' => 'intake', 'source_document' => self::SOURCE.' section 7.1'],
            'Brochure' => ['is_required' => false],
            'Pitch Deck' => ['item_name' => 'Project concept / pitch deck', 'soi_section' => 'intake', 'source_document' => self::SOURCE.' section 7.1'],
            'Formal letter of project proposal addressed to the NDC General Manager' => ['item_name' => 'Letter of Intent (LOI)', 'soi_section' => 'intake', 'source_document' => self::SOURCE.' section 7.1'],
            'Letter of Intent or endorsement letter from other government agency' => ['is_required' => false, 'source_document' => self::SOURCE.' section 7.3 endorsement if any'],
        ];
        foreach ($changes as $name => $values) {
            $project->requirements()->where('item_name', $name)->update($values);
        }
        foreach ([
            'Updated Project Information Memorandum',
            'Financial Model / Basic Profitability Analysis',
            'Proof of ownership / authority to operate over the project',
        ] as $index => $name) {
            $alias = match ($index) { 1 => 'financial model', 2 => 'proof of', default => 'updated project information memorandum' };
            if ($project->requirements()->whereRaw('LOWER(item_name) LIKE ?', ['%'.$alias.'%'])->exists()) {
                continue;
            }
            $project->requirements()->firstOrCreate(['item_name' => $name], [
                'group_name' => '4. Documentary Requirements', 'source_document' => self::SOURCE.' section 7.3.2',
                'track' => $project->process_track, 'owner_type' => 'proponent', 'visibility' => 'proponent_visible',
                'soi_section' => 'requirements', 'is_required' => true, 'is_applicable' => true,
                'status' => $project->currentStage?->name === 'Requirements' ? 'requested' : 'pending', 'sort_order' => 450 + $index,
            ]);
        }
        $project->requirements()->whereIn('item_name', [
            'NDC response letter and documentary checklist issued to proponent',
            'Response letter and documentary checklist issued to proponent',
        ])->update(['gate_step' => 'qualification_reply', 'source_document' => self::SOURCE.' section 7.3']);
        foreach ([
            ['Initial evaluation report submitted to AGM', 'intake', 'initial_review', '7.2'],
            ['Receipt and completeness check of proposal documents', 'requirements', 'due_diligence_report', '7.4'],
            ['Due diligence findings and recommendation endorsed by AGM', 'due_diligence', 'due_diligence_report', '7.5'],
        ] as $index => [$name, $section, $gate, $reference]) {
            $project->requirements()->firstOrCreate(['item_name' => $name], [
                'group_name' => '6. Initial Evaluation', 'source_document' => self::SOURCE.' section '.$reference,
                'track' => $project->process_track, 'owner_type' => 'internal', 'visibility' => 'internal_only',
                'soi_section' => $section, 'gate_step' => $gate, 'is_required' => true, 'is_applicable' => true,
                'status' => 'pending', 'sort_order' => 600 + $index,
            ]);
        }
        // Do not withdraw existing requests or overwrite uploaded/approved evidence on active projects.
        if (!$project->approvals()->exists()) {
            $project->requirements()->where('owner_type', 'proponent')->where('soi_section', '!=', 'intake')
                ->where('status', 'requested')->whereNull('document_id')->update(['status' => 'pending']);
            $project->requirements()->where('owner_type', 'proponent')->where('soi_section', 'intake')
                ->where('status', 'pending')->update(['status' => 'requested']);
        }
    }

    public static function isBoard(ApprovalStep $step): bool
    {
        return str_contains(strtolower($step->step_name), 'board');
    }

    public static function isMancom(ApprovalStep $step): bool
    {
        return str_contains(strtolower($step->step_name), 'mancom');
    }

    public static function allowsDecision(Project $project, ApprovalStep $step): bool
    {
        return self::holdsConditions($project, $step) || (self::applies($project)
            && $step->role?->name === 'Workgroup Head' && str_contains(strtolower($step->step_name), 'response letter'));
    }

    public static function holdsConditions(Project $project, ApprovalStep $step): bool
    {
        return self::applies($project) && (self::isBoard($step) || self::isMancom($step));
    }

    public static function missingDocuments(Project $project, ApprovalStep $step): array
    {
        if (!self::applies($project) || (!self::isBoard($step) && !self::isMancom($step) && !str_contains(strtolower($step->step_name), 'triangulation'))) {
            return [];
        }

        $isBoard = self::isBoard($step);
        $presentation = $project->board_presentation_date;
        // A future meeting date cannot make today's Board decision satisfy the notice period.
        $cutoff = ($presentation && $presentation->isBefore(today()) ? $presentation->copy() : today())->subDays(14);
        return $project->requirements()->where('owner_type', 'proponent')
            ->where('is_required', true)->where('is_applicable', true)
            ->whereIn('soi_section', ['intake', 'requirements', 'due_diligence'])->get()
            ->filter(function ($requirement) use ($isBoard, $cutoff, $presentation) {
                if (!$isBoard && $requirement->status === 'deferred'
                    && $requirement->deferral_authorized_by && $requirement->deferral_authorized_at && $requirement->due_date
                    && $presentation && $requirement->due_date->lte($presentation->copy()->subDays(14))) {
                    return false;
                }
                if (!in_array($requirement->status, ['received', 'approved', 'waived'], true)) {
                    return true;
                }
                return $isBoard && $requirement->deferral_authorized_by
                    && (!$presentation || !$requirement->received_at || $requirement->received_at->copy()->startOfDay()->isAfter($cutoff));
            })->pluck('item_name')->all();
    }
}
