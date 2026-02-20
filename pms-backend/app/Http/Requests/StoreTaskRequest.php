<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'task_type' => 'nullable|string|max:50',
            'assigned_to' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high,critical',
            'estimated_hours' => 'nullable|numeric|min:0',
            'parent_task_id' => 'nullable|exists:tasks,id',
            'is_milestone' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Task title is required.',
            'due_date.after_or_equal' => 'Due date must be on or after the start date.',
        ];
    }
}