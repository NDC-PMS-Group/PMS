<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtendApprovalStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'extension_days' => ['required', 'integer', 'min:1', 'max:365'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'extension_days.max' => 'A single stage extension cannot exceed 365 days.',
            'reason.required' => 'Please provide a reason for extending this stage.',
        ];
    }
}
