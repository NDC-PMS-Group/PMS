<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EvaluateSvfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'evaluations' => 'required|array',
            'evaluations.*.criteria_id' => 'required|exists:svf_evaluation_criteria,id',
            'evaluations.*.score' => 'required|integer|min:0',
            'evaluations.*.comments' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'evaluations.required' => 'At least one evaluation criterion is required.',
            'evaluations.*.score.required' => 'Score is required for all criteria.',
        ];
    }
}