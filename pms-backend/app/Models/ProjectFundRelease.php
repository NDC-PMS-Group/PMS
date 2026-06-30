<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectFundRelease extends Model
{
    use HasFactory;

    public const RELEASED_STATUSES = ['released'];

    protected $fillable = [
        'project_id',
        'requirement_id',
        'task_id',
        'document_id',
        'funding_source_id',
        'soi_section',
        'gate_step',
        'release_type',
        'status',
        'reference_no',
        'payee',
        'approved_amount',
        'amount',
        'release_date',
        'remarks',
        'prepared_by',
        'reviewed_by',
        'reviewed_at',
        'released_by',
        'released_at',
    ];

    protected $casts = [
        'approved_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'release_date' => 'date',
        'reviewed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function requirement()
    {
        return $this->belongsTo(ProjectRequirement::class, 'requirement_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function fundingSource()
    {
        return $this->belongsTo(FundingSource::class);
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function releasedBy()
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
