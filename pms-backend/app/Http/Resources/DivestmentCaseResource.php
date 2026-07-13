<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DivestmentCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $completedPhaseCount = max(array_search($this->phase, \App\Models\DivestmentCase::PHASES, true) ?: 0, 0);

        return [
            'id' => $this->id,
            'case_number' => $this->case_number,
            'project_id' => $this->project_id,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'phase' => $this->phase,
            'next_phase' => $this->nextPhase(),
            'status' => $this->status,
            'exit_strategy' => $this->exit_strategy,
            'target_exit_date' => $this->target_exit_date?->toDateString(),
            'estimated_proceeds' => $this->estimated_proceeds,
            'actual_proceeds' => $this->actual_proceeds,
            'notes' => $this->notes,
            'phase_started_at' => $this->phase_started_at?->toDateTimeString(),
            'progress_percentage' => $this->status === 'closed'
                ? 100
                : (int) round(($completedPhaseCount / (count(\App\Models\DivestmentCase::PHASES) - 1)) * 100),
            'closure_gates' => collect(\App\Models\DivestmentCase::CLOSURE_GATES)->mapWithKeys(fn ($gate) => [
                $gate => $this->{$gate}?->toDateTimeString(),
            ]),
            'missing_closure_gates' => $this->missingClosureGates(),
            'closure_notes' => $this->closure_notes,
            'closed_at' => $this->closed_at?->toDateTimeString(),
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'closed_by' => new UserResource($this->whenLoaded('closedBy')),
            'transitions' => $this->whenLoaded('transitions', fn () => $this->transitions->map(fn ($transition) => [
                'id' => $transition->id,
                'from_phase' => $transition->from_phase,
                'to_phase' => $transition->to_phase,
                'notes' => $transition->notes,
                'transitioned_by' => new UserResource($transition->transitionedBy),
                'transitioned_at' => $transition->transitioned_at?->toDateTimeString(),
            ])),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
