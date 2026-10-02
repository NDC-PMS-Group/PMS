<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

trait ValidatesProjectDetails
{
    private function projectDetailRules(): array
    {
        $rules = [
            'record_type' => ['nullable', Rule::in(['project', 'investment'])],
            'operations_start_date' => 'nullable|date_format:Y-m-d',
            'other_financing' => 'nullable|array|max:20',
            'other_financing.*' => 'array:source,amount',
            'other_financing.*.source' => 'required|string|max:255',
            'other_financing.*.amount' => 'required|numeric|min:0',
            'additional_locations' => 'sometimes|array|max:100',
            'additional_locations.*' => 'array:region_code,region_name,province_code,province_name,address',
            'additional_locations.*.region_code' => 'required|string|max:20',
            'additional_locations.*.region_name' => 'required|string|max:255',
            'additional_locations.*.province_code' => 'nullable|string|max:20',
            'additional_locations.*.province_name' => 'nullable|string|max:255',
            'additional_locations.*.address' => 'nullable|string|max:255',
            'narrative_content' => 'nullable|array:description,project_rationale,company_background,target_beneficiaries,expected_benefits',
            'narrative_content.*' => 'array:tables,images',
            'narrative_content.*.tables' => 'sometimes|array|max:5',
            'narrative_content.*.tables.*' => 'array:caption,rows',
            'narrative_content.*.tables.*.caption' => 'nullable|string|max:255',
            'narrative_content.*.tables.*.rows' => 'required|array|min:1|max:50',
            'narrative_content.*.tables.*.rows.*' => 'required|array|min:1|max:12',
            'narrative_content.*.tables.*.rows.*.*' => 'nullable|string|max:2000',
            'narrative_content.*.images' => 'sometimes|array|max:3',
            'narrative_content.*.images.*' => 'array:caption,data',
            'narrative_content.*.images.*.caption' => 'required|string|max:255',
            'narrative_content.*.images.*.data' => ['bail', 'required', 'string', 'max:700000', function ($attribute, $value, $fail) {
                if (!preg_match('/^data:image\/(png|jpeg|webp);base64,([A-Za-z0-9+\/=]+)$/D', $value, $matches)) {
                    $fail('Upload a PNG, JPEG or WebP image.');
                    return;
                }
                $bytes = base64_decode($matches[2], true);
                $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;
                if (!$info || $info['mime'] !== 'image/'.$matches[1] || strlen($bytes) > 512000 || $info[0] > 4096 || $info[1] > 4096) {
                    $fail('The image must be valid, at most 500 KB and no larger than 4096 × 4096 pixels.');
                }
            }],
        ];

        foreach (['project_type' => 'project_types', 'industry' => 'industries', 'sector' => 'sectors'] as $field => $table) {
            $project = $this->route('project');
            $id = $this->input($field.'_id', $project?->{$field.'_id'});
            $isOther = is_scalar($id) && $id && DB::table($table)->where('id', $id)->where('name', 'Others')->exists();
            $existingOther = $project && (int) $project->{$field.'_id'} === (int) $id
                ? $project->{$field.'_other'} : null;
            $required = $isOther && ($this->exists($field.'_other') || !trim((string) $existingOther));
            $rules[$field.'_other'] = ['nullable', Rule::requiredIf($required), 'string', 'max:255'];
        }

        return $rules;
    }
}
