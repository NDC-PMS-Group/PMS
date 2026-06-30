<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectApproval extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'workflow_id',
        'current_step_id',
        'overall_status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function currentStep()
    {
        return $this->belongsTo(ApprovalStep::class, 'current_step_id');
    }

    public function stepRecords()
    {
        return $this->hasMany(ApprovalStepRecord::class);
    }

    public function scopePending($query)
    {
        return $query->whereIn('overall_status', [
            'pending',
            'initial_completeness_check',
            'for_evaluation',
            'for_workgroup_review',
            'for_mancom_review',
            'for_board_approval',
            'for_neda_icc_review',
            'for_jv_selection',
            'for_fund_release',
            'milestones_setup',
            'for_monitoring_update',
            'for_divestment_approval',
            'for_approval',
        ]);
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('overall_status', [
            'approved',
            'approved_with_conditions',
            'completed',
        ]);
    }
}
