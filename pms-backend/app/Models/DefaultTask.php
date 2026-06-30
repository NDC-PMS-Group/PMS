<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefaultTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'track',
        'title',
        'description',
        'task_type',
        'soi_section',
        'assigned_role',
        'days',
        'priority',
        'is_milestone',
        'parent_task_title',
        'sort_order',
    ];

    protected $casts = [
        'is_milestone' => 'boolean',
        'days' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
