<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDivestmentCaseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('project')) {
            $project = $this->route('project');
            $this->merge(['project_id' => is_object($project) ? $project->getKey() : $project]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'required|integer|exists:projects,id',
            'exit_strategy' => 'required|string|max:10000',
            'target_exit_date' => 'nullable|date',
            'estimated_proceeds' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:10000',
        ];
    }
}
