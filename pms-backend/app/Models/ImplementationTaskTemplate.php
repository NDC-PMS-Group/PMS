<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImplementationTaskTemplate extends Model
{
    protected $fillable = [
        'project_type_id',
        'template_key',
        'workstream',
        'title',
        'description',
        'assigned_role',
        'start_offset_days',
        'duration_days',
        'priority',
        'is_milestone',
        'parent_template_title',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'start_offset_days' => 'integer',
        'duration_days' => 'integer',
        'sort_order' => 'integer',
        'is_milestone' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function projectType()
    {
        return $this->belongsTo(ProjectType::class);
    }
}
