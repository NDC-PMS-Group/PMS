<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalStepRecord extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'project_approval_id',
        'step_id',
        'approver_id',
        'status',
        'comments',
        'conditions',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function projectApproval()
    {
        return $this->belongsTo(ProjectApproval::class);
    }

    public function step()
    {
        return $this->belongsTo(ApprovalStep::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}