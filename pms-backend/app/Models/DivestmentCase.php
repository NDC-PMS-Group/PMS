<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DivestmentCase extends Model
{
    use HasFactory;

    public const PHASES = [
        'assessment',
        'due_diligence',
        'management_approval',
        'board_approval',
        'execution',
        'closure',
    ];

    public const CLOSURE_GATES = [
        'board_approved_at',
        'transfer_completed_at',
        'proceeds_collected_at',
        'closing_documents_completed_at',
    ];

    protected $fillable = [
        'project_id',
        'case_number',
        'phase',
        'status',
        'exit_strategy',
        'target_exit_date',
        'estimated_proceeds',
        'actual_proceeds',
        'notes',
        'phase_started_at',
        'board_approved_at',
        'transfer_completed_at',
        'proceeds_collected_at',
        'closing_documents_completed_at',
        'closure_notes',
        'closed_at',
        'closed_by',
        'created_by',
    ];

    protected $casts = [
        'target_exit_date' => 'date',
        'estimated_proceeds' => 'decimal:2',
        'actual_proceeds' => 'decimal:2',
        'phase_started_at' => 'datetime',
        'board_approved_at' => 'datetime',
        'transfer_completed_at' => 'datetime',
        'proceeds_collected_at' => 'datetime',
        'closing_documents_completed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function transitions()
    {
        return $this->hasMany(DivestmentCaseTransition::class)->orderBy('transitioned_at')->orderBy('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function nextPhase(): ?string
    {
        $index = array_search($this->phase, self::PHASES, true);

        return $index === false ? null : (self::PHASES[$index + 1] ?? null);
    }

    public function missingClosureGates(): array
    {
        return array_values(array_filter(
            self::CLOSURE_GATES,
            fn (string $gate) => !$this->{$gate}
        ));
    }
}
