<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectFundReleaseResource;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectFundRelease;
use App\Models\ProjectRequirement;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectFundReleaseController extends Controller
{
    public function index(Request $request, Project $project)
    {
        if (!$this->canViewProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to view fund releases for this project'], 403);
        }

        $releases = $project->fundReleases()
            ->with(['requirement.document', 'task', 'document', 'fundingSource', 'preparedBy', 'reviewedBy', 'releasedBy'])
            ->latest('release_date')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => ProjectFundReleaseResource::collection($releases),
            'summary' => $this->releaseSummary($project->loadMissing('fundReleases')),
            'anchors' => $this->fundReleaseAnchors($project),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        if (!$this->canEditProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to record fund releases for this project'], 403);
        }

        $validated = $this->validatedPayload($request, $project);
        $anchors = $this->resolveDynamicSoiAnchors($project, $validated);
        $status = $validated['status'] ?? 'draft';

        $release = DB::transaction(function () use ($project, $validated, $anchors, $status, $request) {
            $release = ProjectFundRelease::create([
                ...$validated,
                'project_id' => $project->id,
                'funding_source_id' => $validated['funding_source_id'] ?? $project->funding_source_id,
                'soi_section' => $anchors['soi_section'],
                'gate_step' => $anchors['gate_step'],
                'prepared_by' => $request->user()?->id,
                'released_by' => $status === 'released' ? $request->user()?->id : null,
                'released_at' => $status === 'released' ? now() : null,
            ]);

            $this->syncLinkedWorkflowArtifacts($release, $request->user());

            return $release;
        });

        return (new ProjectFundReleaseResource($release->load([
            'requirement.document', 'task', 'document', 'fundingSource', 'preparedBy', 'releasedBy',
        ])))->response()->setStatusCode(201);
    }

    public function update(Request $request, Project $project, ProjectFundRelease $fundRelease)
    {
        if ((int) $fundRelease->project_id !== (int) $project->id) {
            return response()->json(['message' => 'Fund release does not belong to this project'], 404);
        }

        if (!$this->canEditProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to update fund releases for this project'], 403);
        }

        $validated = $this->validatedPayload($request, $project, true);
        $anchors = $this->resolveDynamicSoiAnchors($project, $validated + $fundRelease->only(['requirement_id', 'task_id', 'document_id']));
        $status = $validated['status'] ?? $fundRelease->status;
        $becameReleased = $status === 'released' && $fundRelease->status !== 'released';

        DB::transaction(function () use ($fundRelease, $validated, $anchors, $status, $becameReleased, $request) {
            $fundRelease->update([
                ...$validated,
                'soi_section' => $anchors['soi_section'],
                'gate_step' => $anchors['gate_step'],
                'released_by' => $becameReleased ? $request->user()?->id : $fundRelease->released_by,
                'released_at' => $becameReleased ? now() : $fundRelease->released_at,
            ]);

            $this->syncLinkedWorkflowArtifacts($fundRelease->refresh(), $request->user());
        });

        return new ProjectFundReleaseResource($fundRelease->load([
            'requirement.document', 'task', 'document', 'fundingSource', 'preparedBy', 'reviewedBy', 'releasedBy',
        ]));
    }

    public function destroy(Request $request, Project $project, ProjectFundRelease $fundRelease)
    {
        if ((int) $fundRelease->project_id !== (int) $project->id) {
            return response()->json(['message' => 'Fund release does not belong to this project'], 404);
        }

        if (!$this->canEditProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to delete fund releases for this project'], 403);
        }

        $fundRelease->delete();

        return response()->json(['message' => 'Fund release removed']);
    }

    public function anchors(Request $request, Project $project)
    {
        if (!$this->canViewProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to view fund release anchors for this project'], 403);
        }

        return response()->json([
            'summary' => $this->releaseSummary($project->loadMissing('fundReleases')),
            'anchors' => $this->fundReleaseAnchors($project),
        ]);
    }

    private function validatedPayload(Request $request, Project $project, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'requirement_id' => [
                'nullable',
                Rule::exists('project_requirements', 'id')->where('project_id', $project->id),
            ],
            'task_id' => [
                'nullable',
                Rule::exists('tasks', 'id')->where('project_id', $project->id),
            ],
            'document_id' => [
                'nullable',
                Rule::exists('documents', 'id')->where('project_id', $project->id),
            ],
            'funding_source_id' => ['nullable', 'exists:funding_sources,id'],
            'release_type' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['draft', 'for_review', 'approved', 'released', 'cancelled'])],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'payee' => ['nullable', 'string', 'max:255'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'amount' => [$sometimes, 'numeric', 'min:0.01'],
            'release_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function resolveDynamicSoiAnchors(Project $project, array $payload): array
    {
        if (!empty($payload['requirement_id'])) {
            $requirement = ProjectRequirement::where('project_id', $project->id)->find($payload['requirement_id']);
            if ($requirement) {
                return [
                    'soi_section' => $requirement->soi_section ?: $this->deriveSoiSection($requirement->item_name),
                    'gate_step' => $requirement->gate_step,
                ];
            }
        }

        if (!empty($payload['task_id'])) {
            $task = Task::where('project_id', $project->id)->find($payload['task_id']);
            if ($task) {
                return [
                    'soi_section' => $task->soi_section ?: Task::deriveSoiSection($task->task_type, $task->title) ?: 'agreement_fund_release',
                    'gate_step' => $this->deriveGateStep($task->title, $task->task_type),
                ];
            }
        }

        $anchor = $this->fundReleaseAnchors($project)['primary'] ?? null;
        if ($anchor) {
            return [
                'soi_section' => $anchor['soi_section'] ?? 'agreement_fund_release',
                'gate_step' => $anchor['gate_step'] ?? 'fund_release',
            ];
        }

        return ['soi_section' => 'agreement_fund_release', 'gate_step' => 'fund_release'];
    }

    private function fundReleaseAnchors(Project $project): array
    {
        $project->loadMissing(['requirements.document', 'tasks']);
        $requirements = $project->requirements
            ->filter(fn (ProjectRequirement $requirement) => $this->looksLikeFundReleaseAnchor(
                $requirement->soi_section,
                $requirement->gate_step,
                $requirement->item_name,
                $requirement->group_name
            ))
            ->values()
            ->map(fn (ProjectRequirement $requirement) => [
                'kind' => 'requirement',
                'id' => $requirement->id,
                'label' => $requirement->item_name,
                'group' => $requirement->group_name,
                'soi_section' => $requirement->soi_section ?: $this->deriveSoiSection($requirement->item_name),
                'gate_step' => $requirement->gate_step,
                'status' => $requirement->status,
                'document_id' => $requirement->document_id,
            ]);

        $tasks = $project->tasks
            ->filter(fn (Task $task) => !$task->is_deleted && $this->looksLikeFundReleaseAnchor(
                $task->soi_section,
                null,
                $task->title,
                $task->task_type
            ))
            ->values()
            ->map(fn (Task $task) => [
                'kind' => 'task',
                'id' => $task->id,
                'label' => $task->title,
                'group' => $task->task_type,
                'soi_section' => $task->soi_section ?: Task::deriveSoiSection($task->task_type, $task->title),
                'gate_step' => $this->deriveGateStep($task->title, $task->task_type),
                'status' => $task->status,
                'document_id' => null,
            ]);

        $all = $requirements->concat($tasks)->values();

        return [
            'primary' => $all->first(),
            'items' => $all,
        ];
    }

    private function looksLikeFundReleaseAnchor(?string $section, ?string $gate, ?string ...$parts): bool
    {
        $text = strtolower(trim(implode(' ', array_filter([$section, $gate, ...$parts]))));

        return str_contains($text, 'fund_release')
            || str_contains($text, 'fund release')
            || str_contains($text, 'fund deployment')
            || str_contains($text, 'release evidence')
            || str_contains($text, 'receipt issued')
            || str_contains($text, 'drawdown')
            || str_contains($text, 'disbursement')
            || $section === 'agreement_fund_release';
    }

    private function deriveSoiSection(?string $text): string
    {
        return Task::deriveSoiSection('fund_release', $text) ?: 'agreement_fund_release';
    }

    private function deriveGateStep(?string ...$parts): ?string
    {
        $text = strtolower(trim(implode(' ', array_filter($parts))));

        if (str_contains($text, 'fund') || str_contains($text, 'receipt') || str_contains($text, 'drawdown') || str_contains($text, 'disbursement')) {
            return 'fund_release';
        }

        return null;
    }

    private function syncLinkedWorkflowArtifacts(ProjectFundRelease $release, ?User $actor): void
    {
        if ($release->status !== 'released') {
            return;
        }

        if ($release->requirement_id) {
            ProjectRequirement::where('id', $release->requirement_id)
                ->where('project_id', $release->project_id)
                ->update([
                    'document_id' => $release->document_id ?: DB::raw('document_id'),
                    'status' => 'received',
                    'received_by' => $actor?->id,
                    'received_at' => $release->release_date
                        ? Carbon::parse($release->release_date)->startOfDay()
                        : now(),
                ]);
        }

        if ($release->task_id) {
            Task::where('id', $release->task_id)
                ->where('project_id', $release->project_id)
                ->update([
                    'status' => 'completed',
                    'progress_percentage' => 100,
                    'completion_date' => $release->release_date ?: now()->toDateString(),
                ]);
        }
    }

    private function releaseSummary(Project $project): array
    {
        $project->loadMissing('fundReleases');
        $target = (float) ($project->ndc_participation ?: $project->target_amount_to_raise ?: $project->estimated_cost ?: 0);
        $released = (float) $project->fundReleases
            ->whereIn('status', ProjectFundRelease::RELEASED_STATUSES)
            ->sum(fn (ProjectFundRelease $release) => (float) $release->amount);

        return [
            'target_amount' => round($target, 2),
            'released_amount' => round($released, 2),
            'remaining_amount' => round(max($target - $released, 0), 2),
            'release_count' => $project->fundReleases->count(),
            'released_count' => $project->fundReleases->whereIn('status', ProjectFundRelease::RELEASED_STATUSES)->count(),
            'progress' => $target > 0 ? min(100, round(($released / $target) * 100)) : 0,
        ];
    }

    private function canViewProject(?User $user, Project $project): bool
    {
        if (!$user) return false;
        if ((int) $project->created_by === (int) $user->id) return true;
        if ($this->isSuperAdmin($user) || $this->hasAnyPermission($user, ['projects.view', 'project.view', 'view_project'])) return true;

        return $project->members()
            ->where('user_id', $user->id)
            ->whereNull('removed_at')
            ->where('can_view', true)
            ->exists();
    }

    private function canEditProject(?User $user, Project $project): bool
    {
        if (!$user) return false;
        if ((int) $project->created_by === (int) $user->id) return true;
        if ($this->isSuperAdmin($user) || $this->hasAnyPermission($user, ['projects.update', 'projects.edit', 'project.update', 'project.edit', 'edit_project'])) return true;

        return $project->members()
            ->where('user_id', $user->id)
            ->whereNull('removed_at')
            ->where('can_edit', true)
            ->exists();
    }

    private function isSuperAdmin(?User $user): bool
    {
        $roleName = strtolower((string) ($user?->defaultRole?->name ?? ''));

        return $user && ((int) $user->default_role_id === 1 || $roleName === 'superadmin');
    }

    private function hasAnyPermission(User $user, array $permissionNames): bool
    {
        return $user->defaultRole()
            ->whereHas('permissions', fn ($query) => $query->whereIn('name', $permissionNames))
            ->exists();
    }
}
