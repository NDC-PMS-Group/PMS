<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDivestmentCaseRequest;
use App\Http\Requests\UpdateDivestmentCaseRequest;
use App\Http\Resources\DivestmentCaseResource;
use App\Models\DivestmentCase;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DivestmentCaseController extends Controller
{
    public function index(Request $request)
    {
        if (!$this->canAccess($request->user())) {
            return response()->json(['message' => 'Unauthorized to access divestment cases'], 403);
        }

        $query = DivestmentCase::query()->with($this->relationships());

        if ($request->filled('phase')) {
            $query->where('phase', $request->string('phase'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($caseQuery) use ($search) {
                $caseQuery->where('case_number', 'like', "%{$search}%")
                    ->orWhereHas('project', function ($projectQuery) use ($search) {
                        $projectQuery->where('project_code', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%")
                            ->orWhere('proponent_name', 'like', "%{$search}%");
                    });
            });
        }

        $cases = $query->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('target_exit_date')
            ->orderByDesc('created_at')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return DivestmentCaseResource::collection($cases);
    }

    public function store(StoreDivestmentCaseRequest $request)
    {
        if (!$this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized to create divestment cases'], 403);
        }

        if (DivestmentCase::where('project_id', $request->integer('project_id'))->exists()) {
            return response()->json(['message' => 'A divestment case already exists for this project.'], 409);
        }

        $case = DB::transaction(function () use ($request) {
            $case = DivestmentCase::create([
                ...$request->validated(),
                'phase' => DivestmentCase::PHASES[0],
                'status' => 'active',
                'phase_started_at' => now(),
                'created_by' => $request->user()->id,
            ]);

            $case->update(['case_number' => sprintf('EXIT-%s-%04d', now()->format('Y'), $case->id)]);
            $case->transitions()->create([
                'from_phase' => null,
                'to_phase' => DivestmentCase::PHASES[0],
                'notes' => 'Divestment case opened.',
                'transitioned_by' => $request->user()->id,
                'transitioned_at' => now(),
            ]);

            $project = Project::findOrFail($case->project_id);
            $project->update([
                'lifecycle_phase' => 'divestment',
                'lifecycle_phase_started_at' => now(),
                'origin_track' => $project->origin_track ?: (in_array($project->process_track, ['bdg_investment', 'spg_traditional', 'spg_ndc_own', 'spg_jv'], true) ? $project->process_track : null),
            ]);

            return $case;
        });

        return (new DivestmentCaseResource($case->load($this->relationships())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, DivestmentCase $divestmentCase)
    {
        if (!$this->canAccess($request->user())) {
            return response()->json(['message' => 'Unauthorized to access this divestment case'], 403);
        }

        return new DivestmentCaseResource($divestmentCase->load($this->relationships()));
    }

    public function update(UpdateDivestmentCaseRequest $request, DivestmentCase $divestmentCase)
    {
        if (!$this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized to update divestment cases'], 403);
        }
        if ($divestmentCase->status === 'closed') {
            return response()->json(['message' => 'Closed divestment cases cannot be changed.'], 409);
        }

        $divestmentCase->update($request->validated());

        return new DivestmentCaseResource($divestmentCase->fresh()->load($this->relationships()));
    }

    public function transition(Request $request, DivestmentCase $divestmentCase)
    {
        if (!$this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized to transition divestment cases'], 403);
        }
        if ($divestmentCase->status !== 'active') {
            return response()->json(['message' => 'Only active divestment cases may transition.'], 409);
        }

        $validated = $request->validate([
            'to_phase' => ['required', 'string', Rule::in(DivestmentCase::PHASES)],
            'notes' => 'required|string|max:5000',
        ]);

        $expectedPhase = $divestmentCase->nextPhase();
        if ($validated['to_phase'] !== $expectedPhase || $validated['to_phase'] === 'closure') {
            return response()->json([
                'message' => $expectedPhase === 'closure'
                    ? 'Use the close action after completing all closure gates.'
                    : "The next allowed phase is {$expectedPhase}.",
            ], 422);
        }

        if ($validated['to_phase'] === 'execution' && !$divestmentCase->board_approved_at) {
            return response()->json([
                'message' => 'Board approval evidence is required before execution.',
                'missing_gates' => ['board_approved_at'],
            ], 422);
        }

        DB::transaction(function () use ($divestmentCase, $validated, $request) {
            $fromPhase = $divestmentCase->phase;
            $divestmentCase->update([
                'phase' => $validated['to_phase'],
                'phase_started_at' => now(),
            ]);
            $divestmentCase->transitions()->create([
                'from_phase' => $fromPhase,
                'to_phase' => $validated['to_phase'],
                'notes' => $validated['notes'],
                'transitioned_by' => $request->user()->id,
                'transitioned_at' => now(),
            ]);
        });

        return new DivestmentCaseResource($divestmentCase->fresh()->load($this->relationships()));
    }

    public function close(Request $request, DivestmentCase $divestmentCase)
    {
        if (!$this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized to close divestment cases'], 403);
        }
        if ($divestmentCase->status === 'closed') {
            return response()->json(['message' => 'This divestment case is already closed.'], 409);
        }
        if ($divestmentCase->phase !== 'execution') {
            return response()->json(['message' => 'The case must be in execution before it can close.'], 422);
        }

        $validated = $request->validate([
            'board_approved_at' => 'required|date',
            'transfer_completed_at' => 'required|date',
            'proceeds_collected_at' => 'required|date',
            'closing_documents_completed_at' => 'required|date',
            'actual_proceeds' => 'required|numeric|min:0',
            'closure_notes' => 'required|string|max:10000',
        ]);

        DB::transaction(function () use ($divestmentCase, $validated, $request) {
            $divestmentCase->update([
                ...$validated,
                'phase' => 'closure',
                'status' => 'closed',
                'phase_started_at' => now(),
                'closed_at' => now(),
                'closed_by' => $request->user()->id,
            ]);
            $divestmentCase->transitions()->create([
                'from_phase' => 'execution',
                'to_phase' => 'closure',
                'notes' => $validated['closure_notes'],
                'transitioned_by' => $request->user()->id,
                'transitioned_at' => now(),
            ]);
            $divestmentCase->project()->update([
                'lifecycle_phase' => 'completed',
                'lifecycle_phase_started_at' => now(),
            ]);
        });

        return new DivestmentCaseResource($divestmentCase->fresh()->load($this->relationships()));
    }

    private function canAccess(?User $user): bool
    {
        return $user && (
            in_array((int) $user->default_role_id, [1, 2], true)
            || $user->hasPermissionTo('admin_tools.view')
            || $user->hasPermissionTo('projects.view')
        );
    }

    private function canManage(?User $user): bool
    {
        return $user && (
            in_array((int) $user->default_role_id, [1, 2], true)
            || $user->hasPermissionTo('admin_tools.update')
            || $user->hasPermissionTo('projects.update')
        );
    }

    private function relationships(): array
    {
        return [
            'project.projectType',
            'project.industry',
            'project.sector',
            'project.currentStage',
            'project.status',
            'project.projectOfficer',
            'project.workgroupHead',
            'project.creator',
            'project.proponentUser',
            'transitions.transitionedBy',
            'creator',
            'closedBy',
        ];
    }
}
