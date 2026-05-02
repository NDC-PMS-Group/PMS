<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Project;
use App\Models\ProjectStage;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'project_type_id' => 'nullable|exists:project_types,id',
            'industry_id' => 'nullable|exists:industries,id',
            'sector_id' => 'nullable|exists:sectors,id',
            'investment_type_id' => 'nullable|exists:investment_types,id',
            'funding_source_id' => 'nullable|exists:funding_sources,id',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'current_stage_id' => 'sometimes|required|exists:project_stages,id',
            'status_id' => 'sometimes|required|exists:project_statuses,id',
            'proposal_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'target_completion_date' => 'nullable|date',
            'actual_completion_date' => 'nullable|date',
            // Legacy flat columns — derived server-side from address.* on save.
            'location_address' => 'nullable|string',
            'location_lat'     => 'nullable|numeric|between:-90,90',
            'location_lng'     => 'nullable|numeric|between:-180,180',

            // ── Structured address (1:1 child) ─────────────────────────────
            'address'                     => 'nullable|array',
            'address.house_number'        => 'nullable|string|max:50',
            'address.floor'               => 'nullable|string|max:50',
            'address.street'              => 'nullable|string|max:255',
            'address.barangay'            => 'required_with:address|string|max:255',
            'address.city_municipality'   => 'required_with:address|string|max:255',
            'address.province'            => 'required_with:address|string|max:255',
            'address.region'              => 'required_with:address|string|max:255',
            'address.country'             => 'nullable|string|max:100',
            'address.zip_code'            => 'nullable|string|max:20',
            'address.latitude'            => 'required_with:address|numeric|between:-90,90',
            'address.longitude'           => 'required_with:address|numeric|between:-180,180',

            'project_officer_id' => 'nullable|exists:users,id',
            'workgroup_head_id' => 'nullable|exists:users,id',
            'proponent_name' => 'nullable|string|max:255',
            'proponent_contact' => 'nullable|string|max:255',
            'proponent_email' => 'nullable|email|max:255',
            'is_svf' => 'boolean',
            'is_archived' => 'boolean',
            'stage_change_reason' => 'nullable|string|max:500',
            'status_change_reason' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'address.region.required_with'             => 'Region is required.',
            'address.province.required_with'           => 'Province is required.',
            'address.city_municipality.required_with'  => 'City / Municipality is required.',
            'address.barangay.required_with'           => 'Barangay is required.',
            'address.latitude.required_with'           => 'Latitude is required.',
            'address.longitude.required_with'          => 'Longitude is required.',
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
        });
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
