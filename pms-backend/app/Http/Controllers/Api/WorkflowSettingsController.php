<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalStep;
use App\Models\DefaultRequirement;
use App\Models\DefaultTask;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class WorkflowSettingsController extends Controller
{
    /**
     * Get all workflows with their steps and roles
     */
    public function indexWorkflows()
    {
        $workflows = ApprovalWorkflow::with(['steps.role'])->get();
        return response()->json([
            'data' => $workflows
        ]);
    }

    /**
     * Update workflow information
     */
    public function updateWorkflow(Request $request, ApprovalWorkflow $workflow)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $workflow->update($validated);

        return response()->json([
            'message' => 'Workflow updated successfully',
            'data' => $workflow->load('steps.role')
        ]);
    }

    /**
     * Update steps of a workflow (reorder, add, edit, or delete steps)
     */
    public function updateSteps(Request $request, ApprovalWorkflow $workflow)
    {
        $validated = $request->validate([
            'steps' => 'required|array',
            'steps.*.id' => 'nullable|integer',
            'steps.*.step_order' => 'required|integer|min:1',
            'steps.*.role_id' => 'required|exists:roles,id',
            'steps.*.step_name' => 'required|string|max:100',
            'steps.*.soi_section' => 'nullable|string|max:80',
            'steps.*.sla_days' => 'nullable|integer|min:1|max:365',
            'steps.*.is_required' => 'boolean',
            'steps.*.can_skip' => 'boolean',
        ]);

        // We will perform updates in a transaction
        \Illuminate\Support\Facades\DB::transaction(function () use ($workflow, $validated) {
            $receivedStepIds = [];

            foreach ($validated['steps'] as $stepData) {
                $step = null;
                if (!empty($stepData['id'])) {
                    $step = ApprovalStep::where('workflow_id', $workflow->id)->find($stepData['id']);
                }

                if ($step) {
                    $step->update([
                        'step_order' => $stepData['step_order'],
                        'role_id' => $stepData['role_id'],
                        'step_name' => $stepData['step_name'],
                        'soi_section' => $stepData['soi_section'] ?? $step->soi_section,
                        'sla_days' => $stepData['sla_days'] ?? null,
                        'is_required' => $stepData['is_required'] ?? true,
                        'can_skip' => $stepData['can_skip'] ?? false,
                    ]);
                } else {
                    $step = ApprovalStep::create([
                        'workflow_id' => $workflow->id,
                        'step_order' => $stepData['step_order'],
                        'role_id' => $stepData['role_id'],
                        'step_name' => $stepData['step_name'],
                        'soi_section' => $stepData['soi_section'] ?? null,
                        'sla_days' => $stepData['sla_days'] ?? null,
                        'is_required' => $stepData['is_required'] ?? true,
                        'can_skip' => $stepData['can_skip'] ?? false,
                    ]);
                }

                $receivedStepIds[] = $step->id;
            }

            // Remove steps that were not sent
            ApprovalStep::where('workflow_id', $workflow->id)
                ->whereNotIn('id', $receivedStepIds)
                ->delete();
        });

        return response()->json([
            'message' => 'Workflow steps updated successfully',
            'data' => $workflow->load('steps.role')
        ]);
    }

    /**
     * Get default requirements/checklist templates
     */
    public function indexDefaultRequirements(Request $request)
    {
        $query = DefaultRequirement::query();

        if ($request->has('track')) {
            $query->where('track', $request->query('track'));
        }

        $requirements = $query->orderBy('track')->orderBy('sort_order')->get();

        return response()->json([
            'data' => $requirements
        ]);
    }

    /**
     * Create a new default requirement
     */
    public function storeDefaultRequirement(Request $request)
    {
        $validated = $request->validate([
            'track' => 'required|string|max:80',
            'group_name' => 'required|string|max:150',
            'item_name' => 'required|string|max:255',
            'source_document' => 'nullable|string|max:150',
            'owner_type' => ['required', Rule::in(['proponent', 'internal'])],
            'visibility' => ['required', Rule::in(['proponent_visible', 'internal_only'])],
            'soi_section' => 'required|string|max:80',
            'gate_step' => 'nullable|string|max:80',
            'is_required' => 'boolean',
            'svf_only' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $requirement = DefaultRequirement::create($validated);

        return response()->json([
            'message' => 'Default requirement template created successfully',
            'data' => $requirement
        ], 201);
    }

    /**
     * Update a default requirement
     */
    public function updateDefaultRequirement(Request $request, $id)
    {
        $requirement = DefaultRequirement::findOrFail($id);

        $validated = $request->validate([
            'group_name' => 'required|string|max:150',
            'item_name' => 'required|string|max:255',
            'source_document' => 'nullable|string|max:150',
            'owner_type' => ['required', Rule::in(['proponent', 'internal'])],
            'visibility' => ['required', Rule::in(['proponent_visible', 'internal_only'])],
            'soi_section' => 'required|string|max:80',
            'gate_step' => 'nullable|string|max:80',
            'is_required' => 'boolean',
            'svf_only' => 'boolean',
            'sort_order' => 'integer',
            'template_file_path' => 'nullable|string|max:255',
        ]);

        $requirement->update($validated);

        return response()->json([
            'message' => 'Default requirement template updated successfully',
            'data' => $requirement
        ]);
    }

    /**
     * Delete a default requirement
     */
    public function destroyDefaultRequirement($id)
    {
        $requirement = DefaultRequirement::findOrFail($id);
        $requirement->delete();

        return response()->json([
            'message' => 'Default requirement template deleted successfully'
        ]);
    }

    /**
     * Upload a template file for a requirement
     */
    public function uploadTemplate(Request $request, $id)
    {
        $request->validate([
            'file' => 'required|file|mimes:docx,xlsx,pdf,doc,xls,zip|max:10240',
        ]);

        $requirement = DefaultRequirement::findOrFail($id);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            
            // Generate clean name
            $cleanName = time() . '_' . preg_replace('/[^A-Za-z0-9\._-]/', '_', $file->getClientOriginalName());
            
            // Store under templates
            $path = $file->storeAs('templates', $cleanName);
            
            $requirement->update([
                'template_file_path' => 'templates/' . $cleanName,
            ]);

            return response()->json([
                'message' => 'Template file uploaded successfully',
                'data' => $requirement
            ]);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }

    /**
     * Serve direct download of template files
     */
    public function downloadTemplate(Request $request)
    {
        $request->validate([
            'file' => 'required|string',
        ]);

        $fileParam = $request->query('file');
        
        // Block directory traversal attempts
        $normalizedPath = str_replace(['..', '\\'], ['', '/'], $fileParam);
        
        if (str_starts_with($normalizedPath, 'templates/')) {
            $normalizedPath = substr($normalizedPath, 10);
        }

        $fullPath = storage_path('app/templates/' . $normalizedPath);

        if (!file_exists($fullPath)) {
            // Check if file sits in root templates folder
            $fallbackFile = basename($normalizedPath);
            $fallbackPath = storage_path('app/templates/' . $fallbackFile);
            
            if (file_exists($fallbackPath)) {
                $fullPath = $fallbackPath;
            } else {
                return response()->json([
                    'message' => 'Document template file not found on the server: ' . $normalizedPath
                ], 404);
            }
        }

        return response()->download($fullPath, basename($fullPath));
    }

    /**
     * Get all default tasks
     */
    public function indexDefaultTasks(Request $request)
    {
        $query = DefaultTask::query();

        if ($request->has('track')) {
            $query->where('track', $request->query('track'));
        }

        $tasks = $query->orderBy('sort_order')->get();

        return response()->json($tasks);
    }

    /**
     * Store a new default task
     */
    public function storeDefaultTask(Request $request)
    {
        $validated = $request->validate([
            'track' => 'required|string|max:80',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'task_type' => 'nullable|string|max:50',
            'soi_section' => 'required|string|max:80',
            'assigned_role' => 'required|string|max:50',
            'days' => 'required|integer|min:0',
            'priority' => 'required|string|max:20',
            'is_milestone' => 'required|boolean',
            'parent_task_title' => 'nullable|string|max:255',
            'sort_order' => 'required|integer',
        ]);

        $task = DefaultTask::create($validated);

        return response()->json([
            'message' => 'Default task template created successfully',
            'task' => $task
        ], 201);
    }

    /**
     * Update a default task
     */
    public function updateDefaultTask(Request $request, $id)
    {
        $task = DefaultTask::findOrFail($id);

        $validated = $request->validate([
            'track' => 'sometimes|required|string|max:80',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'task_type' => 'nullable|string|max:50',
            'soi_section' => 'sometimes|required|string|max:80',
            'assigned_role' => 'sometimes|required|string|max:50',
            'days' => 'sometimes|required|integer|min:0',
            'priority' => 'sometimes|required|string|max:20',
            'is_milestone' => 'sometimes|required|boolean',
            'parent_task_title' => 'nullable|string|max:255',
            'sort_order' => 'sometimes|required|integer',
        ]);

        $task->update($validated);

        return response()->json([
            'message' => 'Default task template updated successfully',
            'task' => $task
        ]);
    }

    /**
     * Delete a default task
     */
    public function destroyDefaultTask($id)
    {
        $task = DefaultTask::findOrFail($id);
        $task->delete();

        return response()->json([
            'message' => 'Default task template deleted successfully'
        ]);
    }
}
