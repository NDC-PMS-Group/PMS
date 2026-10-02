<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LegacyImportBatchResource;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectLegacyDetail;
use App\Models\ProjectStage;
use App\Models\ProjectStageHistory;
use App\Models\ProjectStatus;
use App\Models\ProjectStatusHistory;
use App\Models\User;
use App\Services\LegacyProjectImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class LegacyProjectController extends Controller
{
    public function __construct(private readonly LegacyProjectImportService $importService)
    {
    }

    public function index(Request $request)
    {
        if (!$this->canViewLegacyProjects($request->user())) {
            return response()->json(['message' => 'Unauthorized to view legacy projects'], 403);
        }

        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'source_status' => ['nullable', 'string', 'max:255'],
            'detail_status' => ['nullable', Rule::in([
                ProjectLegacyDetail::STATUS_NEEDS_DETAILS,
                ProjectLegacyDetail::STATUS_IN_PROGRESS,
                ProjectLegacyDetail::STATUS_COMPLETE,
            ])],
            'stage_id' => ['nullable', 'integer', 'exists:project_stages,id'],
            'status_id' => ['nullable', 'integer', 'exists:project_statuses,id'],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'project_officer_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_archived' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', Rule::in(['title', 'updated_at', 'created_at', 'estimated_cost', 'actual_cost'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = Project::query()
            ->where('is_legacy', true)
            ->with([
                'projectType', 'industry', 'sector', 'currentStage', 'status',
                'projectOfficer', 'workgroupHead', 'creator', 'proponentUser',
                'legacyDetail.batch.importedBy', 'legacyDetail.completedBy',
            ])
            ->accessibleTo($request->user(), ['projects.view', 'project.view', 'view_project']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('project_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('location_address', 'like', "%{$search}%")
                    ->orWhere('proponent_name', 'like', "%{$search}%")
                    ->orWhereHas('legacyDetail', fn ($legacyQuery) => $legacyQuery
                        ->where('source_remarks', 'like', "%{$search}%")
                        ->orWhere('source_partner_raw', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('source_status')) {
            $query->whereHas('legacyDetail', fn ($legacyQuery) => $legacyQuery
                ->where('source_status_raw', $request->string('source_status')->toString()));
        }

        if ($request->filled('detail_status')) {
            $query->whereHas('legacyDetail', fn ($legacyQuery) => $legacyQuery
                ->where('detail_status', $request->string('detail_status')->toString()));
        }

        foreach (['stage_id' => 'current_stage_id', 'status_id' => 'status_id', 'sector_id' => 'sector_id', 'project_officer_id' => 'project_officer_id'] as $requestKey => $column) {
            if ($request->filled($requestKey)) {
                $query->where($column, $request->integer($requestKey));
            }
        }

        if ($request->has('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        }

        $query->orderBy(
            $request->get('sort_by', 'updated_at'),
            $request->get('sort_order', 'desc') === 'asc' ? 'asc' : 'desc'
        );

        return ProjectResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function previewImport(Request $request)
    {
        if (!$this->canImportLegacyProjects($request->user())) {
            return response()->json(['message' => 'Unauthorized to import legacy projects'], 403);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        return response()->json([
            'data' => $this->importService->preview($validated['file']),
        ]);
    }

    public function commitImport(Request $request)
    {
        if (!$this->canImportLegacyProjects($request->user())) {
            return response()->json(['message' => 'Unauthorized to import legacy projects'], 403);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $result = $this->importService->commit($validated['file'], $request->user());

        return response()->json([
            'message' => "Imported {$result['created_count']} legacy project(s).",
            'data' => [
                'batch' => new LegacyImportBatchResource($result['batch']),
                'created_count' => $result['created_count'],
                'skipped_count' => $result['skipped_count'],
                'summary' => $result['summary'],
                'projects' => ProjectResource::collection($result['projects']),
            ],
        ], 201);
    }

    public function show(Request $request, Project $project)
    {
        if (!$project->is_legacy || !$this->canViewProject($request->user(), $project)) {
            return response()->json(['message' => 'Legacy project not found or unavailable'], 404);
        }

        $project->load([
            'projectType', 'industry', 'sector', 'investmentType', 'fundingSource',
            'currentStage', 'status', 'projectOfficer', 'workgroupHead', 'creator', 'proponentUser',
            'legacyDetail.batch.importedBy', 'legacyDetail.completedBy',
        ]);

        return new ProjectResource($project);
    }

    public function update(Request $request, Project $project)
    {
        if (!$project->is_legacy || !$this->canEditProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to edit this legacy project'], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'date_of_application' => ['sometimes', 'nullable', 'date'],
            'project_type_id' => ['sometimes', 'nullable', 'integer', 'exists:project_types,id'],
            'industry_id' => ['sometimes', 'nullable', 'integer', 'exists:industries,id'],
            'sector_id' => ['sometimes', 'nullable', 'integer', 'exists:sectors,id'],
            'investment_type_id' => ['sometimes', 'nullable', 'integer', 'exists:investment_types,id'],
            'investment_type_other' => ['sometimes', 'nullable', 'string', 'max:255'],
            'funding_source_id' => ['sometimes', 'nullable', 'integer', 'exists:funding_sources,id'],
            'funding_source_other' => ['sometimes', 'nullable', 'string', 'max:255'],
            'estimated_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'actual_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'target_amount_to_raise' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'ndc_participation' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'ndc_investment_criteria' => ['sometimes', 'nullable', 'array'],
            'ndc_investment_criteria.*' => ['string', 'max:100'],
            'ndc_investment_criteria_other' => ['sometimes', 'nullable', 'string', 'max:255'],
            'project_rationale' => ['sometimes', 'nullable', 'string'],
            'company_background' => ['sometimes', 'nullable', 'string'],
            'target_beneficiaries' => ['sometimes', 'nullable', 'string'],
            'expected_benefits' => ['sometimes', 'nullable', 'string'],
            'issues_problems' => ['sometimes', 'nullable', 'string'],
            'next_steps' => ['sometimes', 'nullable', 'string'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'proposal_date' => ['sometimes', 'nullable', 'date'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'target_completion_date' => ['sometimes', 'nullable', 'date'],
            'actual_completion_date' => ['sometimes', 'nullable', 'date'],
            'location_address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'location_region_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location_province_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location_city_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location_barangay_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'proponent_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'proponent_contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'proponent_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'proponent_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'project_officer_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'workgroup_head_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'current_stage_id' => ['sometimes', 'required', 'integer', 'exists:project_stages,id'],
            'status_id' => ['sometimes', 'required', 'integer', 'exists:project_statuses,id'],
            'detail_status' => ['sometimes', Rule::in([
                ProjectLegacyDetail::STATUS_NEEDS_DETAILS,
                ProjectLegacyDetail::STATUS_IN_PROGRESS,
                ProjectLegacyDetail::STATUS_COMPLETE,
            ])],
        ]);

        DB::transaction(function () use ($validated, $project, $request) {
            $projectFields = collect($validated)->except(['detail_status', 'proponent_user_id'])->all();
            if (array_key_exists('proponent_user_id', $validated)) {
                $proponent = $this->resolveProponentUser($validated['proponent_user_id']);
                $projectFields['proponent_email'] = $proponent?->email;
                if ($proponent && empty($projectFields['proponent_name'])) {
                    $projectFields['proponent_name'] = $proponent->full_name ?: $proponent->email;
                }
            }
            $oldStageId = $project->current_stage_id;
            $oldStatusId = $project->status_id;

            if (array_key_exists('current_stage_id', $projectFields)) {
                $stage = ProjectStage::find($projectFields['current_stage_id']);
                $projectFields['lifecycle_phase'] = match ($stage?->name) {
                    'Implementation & Monitoring' => 'implementation_monitoring',
                    'Divestment' => 'divestment',
                    'Completion' => 'completed',
                    default => 'development',
                };
                $projectFields['lifecycle_phase_started_at'] = now();
            }

            if ($projectFields) {
                $project->update($projectFields);
            }

            if (array_key_exists('current_stage_id', $projectFields) && (int) $oldStageId !== (int) $projectFields['current_stage_id']) {
                ProjectStageHistory::create([
                    'project_id' => $project->id,
                    'from_stage_id' => $oldStageId,
                    'to_stage_id' => $projectFields['current_stage_id'],
                    'changed_by' => $request->user()->id,
                    'change_reason' => 'Legacy project details updated',
                ]);
            }

            if (array_key_exists('status_id', $projectFields) && (int) $oldStatusId !== (int) $projectFields['status_id']) {
                ProjectStatusHistory::create([
                    'project_id' => $project->id,
                    'from_status_id' => $oldStatusId,
                    'to_status_id' => $projectFields['status_id'],
                    'changed_by' => $request->user()->id,
                    'change_reason' => 'Legacy project details updated',
                ]);
            }

            if (array_key_exists('detail_status', $validated)) {
                $legacyDetail = $project->legacyDetail()->firstOrCreate([]);
                $legacyDetail->detail_status = $validated['detail_status'];
                if ($validated['detail_status'] === ProjectLegacyDetail::STATUS_COMPLETE) {
                    $legacyDetail->completed_at = $legacyDetail->completed_at ?: now();
                    $legacyDetail->completed_by = $legacyDetail->completed_by ?: $request->user()->id;
                } else {
                    $legacyDetail->completed_at = null;
                    $legacyDetail->completed_by = null;
                }
                $legacyDetail->save();
            }
        });

        return new ProjectResource($project->fresh()->load([
            'projectType', 'industry', 'sector', 'investmentType', 'fundingSource', 'currentStage', 'status',
            'projectOfficer', 'workgroupHead', 'creator', 'proponentUser',
            'legacyDetail.batch.importedBy', 'legacyDetail.completedBy',
        ]));
    }

    public function complete(Request $request, Project $project)
    {
        if (!$project->is_legacy || !$this->canEditProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to complete this legacy project'], 403);
        }

        $project->legacyDetail()->updateOrCreate(
            ['project_id' => $project->id],
            [
                'detail_status' => ProjectLegacyDetail::STATUS_COMPLETE,
                'completed_at' => now(),
                'completed_by' => $request->user()->id,
            ]
        );

        return new ProjectResource($project->fresh()->load([
            'projectType', 'industry', 'sector', 'investmentType', 'fundingSource', 'currentStage', 'status',
            'projectOfficer', 'workgroupHead', 'creator', 'proponentUser',
            'legacyDetail.batch.importedBy', 'legacyDetail.completedBy',
        ]));
    }

    private function resolveProponentUser(?int $userId): ?User
    {
        if (!$userId) {
            return null;
        }

        $user = User::with('defaultRole')->findOrFail($userId);
        $roleName = strtolower((string) ($user->defaultRole?->name ?? ''));
        if (!$user->is_active || $roleName !== 'proponent') {
            throw ValidationException::withMessages([
                'proponent_user_id' => 'The linked monitoring account must be an active Proponent user.',
            ]);
        }

        return $user;
    }

    private function canViewLegacyProjects(?User $user): bool
    {
        return $this->hasAnyPermission($user, ['projects.view', 'project.view', 'view_project']);
    }

    private function canImportLegacyProjects(?User $user): bool
    {
        return $this->hasAnyPermission($user, ['projects.create', 'project.create', 'create_project']);
    }

    private function canViewProject(?User $user, Project $project): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->isExternalProponent($user)) {
            return $this->isAssignedToProject($user, $project);
        }

        if ($this->hasAnyPermission($user, ['projects.view', 'project.view', 'view_project'])) {
            return true;
        }

        return $this->isAssignedToProject($user, $project);
    }

    private function canEditProject(?User $user, Project $project): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->hasAnyPermission($user, ['projects.update', 'projects.edit', 'project.update', 'project.edit', 'edit_project'])) {
            return true;
        }

        return $project->members()
            ->where('user_id', $user->id)
            ->whereNull('removed_at')
            ->where('can_edit', true)
            ->exists();
    }

    private function hasAnyPermission(?User $user, array $permissionNames): bool
    {
        if (!$user) {
            return false;
        }

        $roleName = strtolower((string) ($user->defaultRole?->name ?? ''));
        if ((int) $user->default_role_id === 1 || $roleName === 'superadmin' || $user->hasRole('superadmin')) {
            return true;
        }

        foreach ($permissionNames as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    private function isExternalProponent(User $user): bool
    {
        return (int) $user->default_role_id === 7
            || $user->hasRole('Proponent')
            || strtolower((string) ($user->defaultRole?->name ?? '')) === 'proponent';
    }

    private function isAssignedToProject(User $user, Project $project): bool
    {
        return (int) $project->created_by === (int) $user->id
            || strcasecmp((string) $project->proponent_email, (string) $user->email) === 0
            || (int) $project->project_officer_id === (int) $user->id
            || (int) $project->workgroup_head_id === (int) $user->id
            || $project->members()
                ->where('user_id', $user->id)
                ->whereNull('removed_at')
                ->where('can_view', true)
                ->exists();
    }
}
