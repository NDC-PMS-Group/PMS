<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ProjectStage;
use Illuminate\Validation\Validator;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_type_id' => 'nullable|exists:project_types,id',
            'industry_id' => 'nullable|exists:industries,id',
            'sector_id' => 'nullable|exists:sectors,id',
            'investment_type_id' => 'nullable|exists:investment_types,id',
            'funding_source_id' => 'nullable|exists:funding_sources,id',
            'estimated_cost' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'current_stage_id' => 'required|exists:project_stages,id',
            'status_id' => 'required|exists:project_statuses,id',
            'proposal_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'target_completion_date' => 'nullable|date|after_or_equal:start_date',
            // Legacy flat columns — derived server-side from address.* on save,
            // but accepted here for backward compatibility with older clients.
            'location_address' => 'nullable|string',
            'location_lat'     => 'nullable|numeric|between:-90,90',
            'location_lng'     => 'nullable|numeric|between:-180,180',

            // ── Structured address (1:1 child row) ─────────────────────────
            // Optional: the address block itself can be omitted on draft saves.
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
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Project title is required.',
            'target_completion_date.after_or_equal' => 'Target completion date must be on or after the start date.',
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
            $stageId = (int) $this->input('current_stage_id');
            if (!$stageId) {
                return;
            }

            $stage = ProjectStage::find($stageId);
            if (!$stage) {
                return;
            }

            $flow = config('project_workflow.stages', []);
            $firstStage = $flow[0] ?? 'Proposal';

            if ($stage->name !== $firstStage) {
                $validator->errors()->add(
                    'current_stage_id',
                    "New projects must start at {$firstStage} stage."
                );
            }

            $this->validateRequiredFieldsForStage($validator, $stage->name);
        });
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
