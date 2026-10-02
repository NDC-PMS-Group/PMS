<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectAgreementForm extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_RETURNED = 'returned';

    protected $fillable = [
        'project_id',
        'project_approval_id',
        'approval_step_id',
        'document_id',
        'agreement_type',
        'parties',
        'term_sheet',
        'status',
        'return_reason',
        'prepared_by',
        'submitted_by',
        'returned_by',
        'submitted_at',
        'returned_at',
    ];

    protected $casts = [
        'parties' => 'array',
        'submitted_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function approval()
    {
        return $this->belongsTo(ProjectApproval::class, 'project_approval_id');
    }

    public function approvalStep()
    {
        return $this->belongsTo(ApprovalStep::class, 'approval_step_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }
}
