<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'soi_section' => ['nullable', 'string', Rule::in(Task::SOI_SECTIONS)],
            'workstream' => 'nullable|string|max:100',
            'assigned_to' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'priority' => 'nullable|in:low,normal,high,urgent,medium,critical',
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
