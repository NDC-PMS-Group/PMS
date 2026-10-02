<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectMonitoringCycle extends Model
{
    use HasFactory;

    public const COMPLIANCE_TYPES = ['employment', 'financial', 'progress'];

    protected $fillable = [
        'project_id',
        'reporting_year',
        'quarter',
        'period_start',
        'period_end',
        'due_date',
        'instructions',
        'requested_compliance_types',
        'status',
        'opened_by',
        'opened_at',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'reporting_year' => 'integer',
        'quarter' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'date',
        'requested_compliance_types' => 'array',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function reports()
    {
        return $this->hasMany(ProjectMonitoringReport::class, 'monitoring_cycle_id');
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
