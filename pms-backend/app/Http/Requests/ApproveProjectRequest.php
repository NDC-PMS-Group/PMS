<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:approved,approved_with_conditions,deferred,disapproved',
            'comments' => 'required_if:status,deferred,disapproved|nullable|string|max:10000',
            'conditions' => 'required_if:status,approved_with_conditions|nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'conditions.required_if' => 'Please specify the conditions for approval.',
        ];
    }
}
