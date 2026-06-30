<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'required|exists:projects,id',
            'task_id' => 'nullable|exists:tasks,id',
            'requirement_id' => 'nullable|exists:project_requirements,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,webp|max:10240',
            'category' => 'nullable|string|max:100',
            'is_public' => 'boolean',
            'requires_approval' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.max' => 'File size must not exceed 10MB.',
            'file.mimes' => 'Upload a PDF, Word, Excel, CSV, or image file only.',
        ];
    }
}
