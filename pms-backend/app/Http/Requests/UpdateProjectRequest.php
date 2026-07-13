<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Project;
use App\Models\ProjectStage;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $track = $this->input('process_track');
        $originTrack = $this->input('origin_track');

        if (!$track && $originTrack) {
            $this->merge(['process_track' => $originTrack]);
        } elseif ($track && !$originTrack && in_array($track, self::ORIGIN_TRACKS, true)) {
            $this->merge(['origin_track' => $track]);
        }

        if ($track && !$this->has('lifecycle_phase')) {
            $this->merge(['lifecycle_phase' => match ($track) {
                'implementation_monitoring' => 'implementation_monitoring',
                'divestment' => 'divestment',
                default => 'development',
            }]);
        }
    }

    private const ORIGIN_TRACKS = ['bdg_investment', 'spg_traditional', 'spg_ndc_own', 'spg_jv'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'process_track' => 'nullable|string|in:bdg_investment,spg_traditional,spg_ndc_own,spg_jv,implementation_monitoring,divestment',
            'origin_track' => 'nullable|string|in:bdg_investment,spg_traditional,spg_ndc_own,spg_jv',
            'lifecycle_phase' => 'nullable|string|in:development,implementation_monitoring,post_investment,divestment,completed',
            'date_of_application' => 'nullable|date',
            'project_type_id' => 'nullable|exists:project_types,id',
            'industry_id' => 'nullable|exists:industries,id',
            'sector_id' => 'nullable|exists:sectors,id',
            'investment_type_id' => 'nullable|exists:investment_types,id',
            'funding_source_id' => 'nullable|exists:funding_sources,id',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
            'target_amount_to_raise' => 'nullable|numeric|min:0',
            'ndc_participation' => 'nullable|numeric|min:0',
            'ndc_investment_criteria' => 'nullable|array',
            'ndc_investment_criteria.*' => 'string|in:pioneering,developmental,sustainable,inclusive,innovative,board_priority,urgent_special,pgs_commitment',
            'project_rationale' => 'nullable|string',
            'company_background' => 'nullable|string',
            'target_beneficiaries' => 'nullable|string',
            'expected_benefits' => 'nullable|string',
            'risk_analysis' => 'nullable|string',
            'financial_metrics' => 'nullable|array',
            'implementation_milestones' => 'nullable|array',
            'issues_problems' => 'nullable|string',
            'next_steps' => 'nullable|string',
            'post_investment_strategy' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
            'current_stage_id' => 'sometimes|required|exists:project_stages,id',
            'status_id' => 'sometimes|required|exists:project_statuses,id',
            'proposal_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'target_completion_date' => 'nullable|date',
            'actual_completion_date' => 'nullable|date',
            'location_address' => 'nullable|string',
            'location_region_code' => 'nullable|string|max:20',
            'location_region_name' => 'nullable|string|max:255',
            'location_province_code' => 'nullable|string|max:20',
            'location_province_name' => 'nullable|string|max:255',
            'location_city_code' => 'nullable|string|max:20',
            'location_city_name' => 'nullable|string|max:255',
            'location_barangay_code' => 'nullable|string|max:20',
            'location_barangay_name' => 'nullable|string|max:255',
            'location_street' => 'nullable|string|max:255',
            'location_lat' => 'nullable|numeric|between:-90,90',
            'location_lng' => 'nullable|numeric|between:-180,180',
            'project_officer_id' => 'nullable|exists:users,id',
            'workgroup_head_id' => 'nullable|exists:users,id',
            'proponent_name' => 'nullable|string|max:255',
            'proponent_contact' => 'nullable|string|max:255',
            'proponent_email' => 'nullable|email|max:255',
            'is_archived' => 'boolean',
            'stage_change_reason' => 'nullable|string|max:500',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Project|null $project */
            $project = $this->route('project');
            if (!$project) {
                return;
            }

            $targetStageId = $this->has('current_stage_id')
                ? (int) $this->input('current_stage_id')
                : (int) $project->current_stage_id;

            if (!$targetStageId) {
                return;
            }

            $targetStage = ProjectStage::find($targetStageId);
            if (!$targetStage) {
                return;
            }

            $currentStage = ProjectStage::find((int) $project->current_stage_id);
            if ($currentStage && $this->has('current_stage_id')) {
                $this->validateStageTransition($validator, $currentStage, $targetStage);
            }

            $this->validateRequiredFieldsForStage($validator, $targetStage->name, $project);
            $this->validateInvestmentCriteria($validator, $project);
        });
    }

    private function validateInvestmentCriteria(Validator $validator, Project $project): void
    {
        $track = $this->input('process_track', $project->process_track ?? 'bdg_investment');
        if (!in_array($track, ['bdg_investment', 'spg_traditional', 'spg_jv'], true)) {
            return;
        }

        $criteria = $this->has('ndc_investment_criteria')
            ? (array) $this->input('ndc_investment_criteria', [])
            : (array) ($project->ndc_investment_criteria ?? []);

        if (count(array_unique(array_filter($criteria))) < 3) {
            $validator->errors()->add(
                'ndc_investment_criteria',
                'NDC investment projects must satisfy at least three SOI criteria.'
            );
        }
    }

    private function validateStageTransition(Validator $validator, ProjectStage $fromStage, ProjectStage $toStage): void
    {
        if ((int)$fromStage->id === (int)$toStage->id) {
            return;
        }

        $flow = config('project_workflow.stages', []);
        $flowIndex = array_flip($flow);
        $fromIndex = $flowIndex[$fromStage->name] ?? null;
        $toIndex = $flowIndex[$toStage->name] ?? null;

        if ($fromIndex === null || $toIndex === null) {
            $validator->errors()->add(
                'current_stage_id',
                'Invalid stage detected in workflow definition.'
            );
            return;
        }

        if ($toIndex !== ($fromIndex + 1)) {
            $validator->errors()->add(
                'current_stage_id',
                "Invalid stage transition. Allowed next stage after {$fromStage->name} is " . ($flow[$fromIndex + 1] ?? 'N/A') . '.'
            );
        }

        if (!$this->filled('stage_change_reason')) {
            $validator->errors()->add('stage_change_reason', 'Stage change reason is required when moving to the next stage.');
        }
    }

    private function validateRequiredFieldsForStage(Validator $validator, string $stageName, Project $project): void
    {
        $requiredByStage = config('project_workflow.required_fields', []);
        $fieldLabels = config('project_workflow.field_labels', []);
        $requiredFields = $requiredByStage[$stageName] ?? [];

        foreach ($requiredFields as $field) {
            $incoming = $this->input($field);
            $existing = $project->{$field} ?? null;
            $value = $this->has($field) ? $incoming : $existing;
            $missing = $value === null || $value === '' || $value === 0 || $value === '0';

            if ($missing) {
                $label = $fieldLabels[$field] ?? str_replace('_', ' ', $field);
                $validator->errors()->add($field, "The {$label} is required for {$stageName} stage.");
            }
        }
    }
}
