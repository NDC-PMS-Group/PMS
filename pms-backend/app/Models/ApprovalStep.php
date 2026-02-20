<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalStep extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'workflow_id',
        'step_order',
        'role_id',
        'step_name',
        'is_required',
        'can_skip',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'is_required' => 'boolean',
        'can_skip' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}