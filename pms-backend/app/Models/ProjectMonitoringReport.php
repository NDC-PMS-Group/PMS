<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectMonitoringReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'monitoring_cycle_id',
        'reporting_year',
        'quarter',
        'compliance_type',
        'period_start',
        'period_end',
        'employment_period_start',
        'employment_period_end',
        'financial_period_start',
        'financial_period_end',
        'narrative_period_start',
        'narrative_period_end',
        'due_date',
        'status',
        'jobs_generated',
        'jobs_generated_male',
        'jobs_generated_female',
        'jobs_retained',
        'jobs_retained_male',
        'jobs_retained_female',
        'revenue',
        'remittance',
        'milestones',
        'impact',
        'monitoring_narrative',
        'review_notes',
        'created_by',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reporting_year' => 'integer',
        'quarter' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'employment_period_start' => 'date',
        'employment_period_end' => 'date',
        'financial_period_start' => 'date',
        'financial_period_end' => 'date',
        'narrative_period_start' => 'date',
        'narrative_period_end' => 'date',
        'due_date' => 'date',
        'jobs_generated' => 'integer',
        'jobs_generated_male' => 'integer',
        'jobs_generated_female' => 'integer',
        'jobs_retained' => 'integer',
        'jobs_retained_male' => 'integer',
        'jobs_retained_female' => 'integer',
        'revenue' => 'decimal:2',
        'remittance' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function cycle()
    {
        return $this->belongsTo(ProjectMonitoringCycle::class, 'monitoring_cycle_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
