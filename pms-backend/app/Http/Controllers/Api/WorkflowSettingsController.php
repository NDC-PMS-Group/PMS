<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalStep;
use App\Models\DefaultRequirement;
use App\Models\DefaultTask;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WorkflowSettingsController extends Controller
{
    /**
     * Get all workflows with their steps and roles
     */
    public function indexWorkflows()
    {
        $workflows = ApprovalWorkflow::with(['steps.role', 'parentWorkflow'])->orderBy('id')->get();
        return response()->json([
            'data' => $workflows
        ]);
    }

    public function storeWorkflow(Request $request)
    {
        $validated = $request->validate([
            'display_name' => 'required|string|max:120|unique:approval_workflows,display_name',
            'description' => 'nullable|string|max:1000',
            'workflow_group' => ['required', Rule::in(['origin', 'lifecycle'])],
            'entry_action' => ['nullable', Rule::in(['manual_transition', 'start_implementation', 'open_divestment_case'])],
            'audiences' => 'nullable|array',
            'audiences.*' => [Rule::in(['internal', 'proponent'])],
        ]);

        $baseKey = Str::of($validated['display_name'])->slug('_')->limit(64, '')->toString() ?: 'workflow';
        $key = $baseKey;
        $suffix = 2;
        while (ApprovalWorkflow::query()->where('workflow_key', $key)->exists()) {
            $key = $baseKey . '_' . $suffix++;
        }

        $workflow = ApprovalWorkflow::create([
            'workflow_key' => $key,
            'workflow_group' => $validated['workflow_group'],
            'display_name' => trim($validated['display_name']),
            'name' => trim($validated['display_name']),
            'description' => $validated['description'] ?? null,
            'entry_action' => $validated['workflow_group'] === 'lifecycle'
                ? ($validated['entry_action'] ?? 'manual_transition')
                : null,
            'audiences' => $validated['workflow_group'] === 'origin'
                ? ($validated['audiences'] ?? ['internal'])
                : ['internal'],
            'is_active' => false,
        ]);

        return response()->json([
            'message' => 'Workflow created successfully',
            'data' => $workflow->load(['steps.role', 'parentWorkflow']),
        ], 201);
    }

    /**
     * Update workflow information
     */
    public function updateWorkflow(Request $request, ApprovalWorkflow $workflow)
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120', Rule::unique('approval_workflows', 'display_name')->ignore($workflow->id)],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'entry_action' => ['nullable', Rule::in(['manual_transition', 'start_implementation', 'open_divestment_case'])],
            'audiences' => 'nullable|array',
            'audiences.*' => [Rule::in(['internal', 'proponent'])],
        ]);

        if ($workflow->workflow_group !== 'lifecycle') {
            unset($validated['entry_action']);
        }

        if (($validated['is_active'] ?? false) && ! $workflow->steps()->exists()) {
            return response()->json([
                'message' => 'Add at least one approval step before activating this workflow.',
            ], 422);
        }

        $workflow->update($validated);

        return response()->json([
            'message' => 'Workflow updated successfully',
            'data' => $workflow->load(['steps.role', 'parentWorkflow'])
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
            'steps.*.requires_agreement_form' => 'boolean',
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
                        'requires_agreement_form' => $stepData['requires_agreement_form'] ?? false,
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
                        'requires_agreement_form' => $stepData['requires_agreement_form'] ?? false,
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
        $query = DefaultRequirement::query()->with('responsibleRole:id,name');

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
            'responsible_role_id' => 'nullable|required_if:owner_type,internal|exists:roles,id',
            'visibility' => ['required', Rule::in(['proponent_visible', 'internal_only'])],
            'soi_section' => 'required|string|max:80',
            'gate_step' => 'nullable|string|max:80',
            'is_required' => 'boolean',
            'svf_only' => 'boolean',
            'sort_order' => 'integer',
        ]);

        if (($validated['owner_type'] ?? null) !== 'internal') {
            $validated['responsible_role_id'] = null;
        }
        $requirement = DefaultRequirement::create($validated);

        return response()->json([
            'message' => 'Default requirement template created successfully',
            'data' => $requirement->load('responsibleRole:id,name')
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
            'responsible_role_id' => 'nullable|required_if:owner_type,internal|exists:roles,id',
            'visibility' => ['required', Rule::in(['proponent_visible', 'internal_only'])],
            'soi_section' => 'required|string|max:80',
            'gate_step' => 'nullable|string|max:80',
            'is_required' => 'boolean',
            'svf_only' => 'boolean',
            'sort_order' => 'integer',
            'template_file_path' => 'nullable|string|max:255',
        ]);

        if (($validated['owner_type'] ?? null) !== 'internal') {
            $validated['responsible_role_id'] = null;
        }
        $requirement->update($validated);

        return response()->json([
            'message' => 'Default requirement template updated successfully',
            'data' => $requirement->load('responsibleRole:id,name')
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

    public function reorderDefaultTasks(Request $request)
    {
        $validated = $request->validate([
            'track' => 'required|string|max:80',
            'soi_section' => 'required|string|max:80',
            'tasks' => 'required|array|min:1',
            'tasks.*.id' => 'required|integer|distinct|exists:default_tasks,id',
            'tasks.*.sort_order' => 'required|integer|min:1',
            'tasks.*.parent_task_title' => 'nullable|string|max:255',
        ]);

        $taskIds = collect($validated['tasks'])->pluck('id');
        $ownedCount = DefaultTask::query()
            ->whereIn('id', $taskIds)
            ->where('track', $validated['track'])
            ->where('soi_section', $validated['soi_section'])
            ->count();

        if ($ownedCount !== $taskIds->unique()->count()) {
            return response()->json([
                'message' => 'Every reordered task must belong to the selected workflow phase.',
            ], 422);
        }

        $titles = DefaultTask::query()
            ->where('track', $validated['track'])
            ->where('soi_section', $validated['soi_section'])
            ->whereIn('id', $taskIds)
            ->pluck('title', 'id');

        $seenParentTitles = collect();
        foreach ($validated['tasks'] as $item) {
            if ($item['parent_task_title'] && ! $titles->contains($item['parent_task_title'])) {
                return response()->json([
                    'message' => 'Indented tasks must reference a parent in the same workflow phase.',
                ], 422);
            }

            if ($item['parent_task_title'] === $titles->get($item['id'])) {
                return response()->json(['message' => 'A task cannot be its own parent.'], 422);
            }

            if ($item['parent_task_title'] && ! $seenParentTitles->contains($item['parent_task_title'])) {
                return response()->json([
                    'message' => 'A parent task must appear before its indented checklist items.',
                ], 422);
            }

            $seenParentTitles->push($titles->get($item['id']));
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated['tasks'] as $item) {
                DefaultTask::query()->whereKey($item['id'])->update([
                    'sort_order' => $item['sort_order'],
                    'parent_task_title' => $item['parent_task_title'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Work-plan task order saved.',
            'tasks' => DefaultTask::query()
                ->where('track', $validated['track'])
                ->where('soi_section', $validated['soi_section'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
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
