<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSvfApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'startup_name' => 'required|string|max:255',
            'startup_description' => 'required|string',
            'founder_name' => 'required|string|max:255',
            'founder_email' => 'required|email|max:255',
            'founder_phone' => 'nullable|string|max:50',
            'requested_amount' => 'required|numeric|min:0',
            'submitted_via' => 'nullable|in:web,portal,manual',
        ];
    }
}