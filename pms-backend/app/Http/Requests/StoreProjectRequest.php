<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ProjectStage;
use Illuminate\Validation\Validator;

class StoreProjectRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $originTrack = $this->input('origin_track') ?: $this->input('process_track') ?: 'bdg_investment';

        $this->merge([
            'process_track' => $originTrack,
            'origin_track' => $originTrack,
            'lifecycle_phase' => 'development',
            'is_svf' => $originTrack === 'bdg_investment' && $this->boolean('is_svf'),
        ]);
    }

    private const ORIGIN_TRACKS = ['bdg_investment', 'spg_traditional', 'spg_ndc_own', 'spg_jv'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'process_track' => 'required|string|in:bdg_investment,spg_traditional,spg_ndc_own,spg_jv',
            'origin_track' => 'required|string|in:bdg_investment,spg_traditional,spg_ndc_own,spg_jv',
            'lifecycle_phase' => 'required|string|in:development',
            'date_of_application' => 'nullable|date',
            'project_type_id' => 'nullable|exists:project_types,id',
            'industry_id' => 'nullable|exists:industries,id',
            'sector_id' => 'nullable|exists:sectors,id',
            'investment_type_id' => 'nullable|exists:investment_types,id',
            'funding_source_id' => 'nullable|exists:funding_sources,id',
            'estimated_cost' => 'nullable|numeric|min:0',
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
            'current_stage_id' => 'required|exists:project_stages,id',
            'status_id' => 'required|exists:project_statuses,id',
            'proposal_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'target_completion_date' => 'nullable|date|after_or_equal:start_date',
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
            'is_svf' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Project title is required.',
            'target_completion_date.after_or_equal' => 'Target completion date must be on or after the start date.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $stageId = (int) $this->input('current_stage_id');
            if (!$stageId) {
                return;
            }

            $stage = ProjectStage::find($stageId);
            if (!$stage) {
                return;
            }

            $firstStage = $this->initialStageForTrack((string) $this->input('process_track', 'bdg_investment'));

            if ($stage->name !== $firstStage) {
                $validator->errors()->add(
                    'current_stage_id',
                    "New projects must start at {$firstStage} stage."
                );
            }

            $this->validateRequiredFieldsForStage($validator, $stage->name);
            $this->validateInvestmentCriteria($validator);
        });
    }

    private function validateInvestmentCriteria(Validator $validator): void
    {
        $track = $this->input('process_track', 'bdg_investment');
        if (!in_array($track, ['bdg_investment', 'spg_traditional', 'spg_jv'], true)) {
            return;
        }

        $criteria = array_filter((array) $this->input('ndc_investment_criteria', []));
        if (count(array_unique($criteria)) < 3) {
            $validator->errors()->add(
                'ndc_investment_criteria',
                'NDC investment projects must satisfy at least three SOI criteria.'
            );
        }
    }

    private function initialStageForTrack(string $track): string
    {
        return config('project_workflow.stages.0', 'Intake');
    }

    private function validateRequiredFieldsForStage(Validator $validator, string $stageName): void
    {
        $requiredByStage = config('project_workflow.required_fields', []);
        $fieldLabels = config('project_workflow.field_labels', []);
        $requiredFields = $requiredByStage[$stageName] ?? [];

        foreach ($requiredFields as $field) {
            $value = $this->input($field);
            $missing = $value === null || $value === '' || $value === 0 || $value === '0';
            if ($missing) {
                $label = $fieldLabels[$field] ?? str_replace('_', ' ', $field);
                $validator->errors()->add($field, "The {$label} is required for {$stageName} stage.");
            }
        }
    }
}
