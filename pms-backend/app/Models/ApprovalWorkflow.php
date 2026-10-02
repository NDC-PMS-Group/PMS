<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'workflow_key',
        'workflow_group',
        'display_name',
        'description',
        'project_type_id',
        'parent_workflow_id',
        'entry_action',
        'audiences',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'audiences' => 'array',
        'created_at' => 'datetime',
    ];

    public function projectType()
    {
        return $this->belongsTo(ProjectType::class);
    }

    public function steps()
    {
        return $this->hasMany(ApprovalStep::class, 'workflow_id')->orderBy('step_order');
    }

    public function parentWorkflow()
    {
        return $this->belongsTo(self::class, 'parent_workflow_id');
    }

    public function variants()
    {
        return $this->hasMany(self::class, 'parent_workflow_id')->orderBy('id');
    }

    public function projectApprovals()
    {
        return $this->hasMany(ProjectApproval::class, 'workflow_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
