<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectApprovalStepExtension extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_approval_id',
        'approval_step_id',
        'extended_by',
        'extension_days',
        'previous_due_at',
        'new_due_at',
        'reason',
    ];

    protected $casts = [
        'extension_days' => 'integer',
        'previous_due_at' => 'datetime',
        'new_due_at' => 'datetime',
    ];

    public function approval()
    {
        return $this->belongsTo(ProjectApproval::class, 'project_approval_id');
    }

    public function step()
    {
        return $this->belongsTo(ApprovalStep::class, 'approval_step_id');
    }

    public function extendedBy()
    {
        return $this->belongsTo(User::class, 'extended_by');
    }
}
