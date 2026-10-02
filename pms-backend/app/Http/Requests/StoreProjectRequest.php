<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ProjectStage;
use App\Models\ProjectType;
use App\Models\ApprovalWorkflow;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Support\ProjectCategory;

class StoreProjectRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ValidatesProjectDetails;

    protected function prepareForValidation(): void
    {
        $selectedCategory = (string) ($this->input('origin_track') ?: $this->input('process_track') ?: ProjectCategory::TRADITIONAL_EXTERNAL);
        $isStartup = ProjectCategory::isStartup($selectedCategory)
            || ($selectedCategory === 'bdg_investment' && $this->boolean('is_svf'));
        $originTrack = ProjectCategory::storageTrack($selectedCategory);
        $criteria = (array) $this->input('ndc_investment_criteria', []);

        $this->merge([
            'process_track' => $originTrack,
            'origin_track' => $originTrack,
            'lifecycle_phase' => 'development',
            'is_svf' => $isStartup,
            'ndc_investment_criteria_other' => in_array('others', $criteria, true)
                ? $this->input('ndc_investment_criteria_other')
                : null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->projectDetailRules(),
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'process_track' => ['required', 'string', 'max:80', Rule::in($this->originTrackKeys())],
            'origin_track' => ['required', 'string', 'max:80', Rule::in($this->originTrackKeys())],
            'lifecycle_phase' => 'required|string|in:development',
            'date_of_application' => 'nullable|date',
            'project_type_id' => 'nullable|exists:project_types,id',
            'industry_id' => 'nullable|exists:industries,id',
            'sector_id' => 'nullable|exists:sectors,id',
            'investment_type_id' => 'nullable|exists:investment_types,id',
            'investment_type_other' => 'nullable|required_if:investment_type_id,' . $this->lookupId('investment_types', 'Others') . '|string|max:255',
            'funding_source_id' => 'nullable|exists:funding_sources,id',
            'funding_source_other' => 'nullable|required_if:funding_source_id,' . $this->lookupId('funding_sources', 'Others') . '|string|max:255',
            'estimated_cost' => 'nullable|numeric|min:0',
            'target_amount_to_raise' => 'nullable|numeric|min:0',
            'ndc_participation' => 'nullable|numeric|min:0',
            'ndc_investment_criteria' => 'nullable|array',
            'ndc_investment_criteria.*' => ['string', Rule::in($this->investmentCriteriaKeys())],
            'ndc_investment_criteria_other' => 'nullable|string|max:255',
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

    private function lookupId(string $table, string $name): int
    {
        return (int) \Illuminate\Support\Facades\DB::table($table)->where('name', $name)->value('id');
    }

    private function investmentCriteriaKeys(): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('investment_criteria')) {
            return ['pioneering', 'developmental', 'sustainable', 'inclusive', 'innovative', 'board_priority', 'urgent_special', 'pgs_commitment', 'others'];
        }

        return \Illuminate\Support\Facades\DB::table('investment_criteria')
            ->where('is_active', true)
            ->pluck('key')
            ->all();
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
            $projectTypeId = (int) $this->input('project_type_id');
            if ($projectTypeId && ProjectType::whereKey($projectTypeId)->where('name', 'SVF Project')->exists()) {
                $validator->errors()->add(
                    'project_type_id',
                    'Startup Venture is a project category, not a project type.'
                );
            }

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

        if (in_array('others', $criteria, true) && !trim((string) $this->input('ndc_investment_criteria_other'))) {
            $validator->errors()->add(
                'ndc_investment_criteria_other',
                'Define the other NDC investment criterion.'
            );
        }
    }

    private function initialStageForTrack(string $track): string
    {
        return config('project_workflow.stages.0', 'Intake');
    }

    private function originTrackKeys(): array
    {
        $keys = ApprovalWorkflow::query()
            ->where('workflow_group', 'origin')
            ->where('is_active', true)
            ->whereNotNull('workflow_key')
            ->whereHas('steps')
            ->pluck('workflow_key')
            ->values();

        if ($keys->isNotEmpty()) {
            return $keys->all();
        }

        return ApprovalWorkflow::query()->whereNotNull('workflow_key')->exists()
            ? []
            : ['bdg_investment', 'bdg_svf', 'spg_ndc_own', 'spg_jv'];
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
