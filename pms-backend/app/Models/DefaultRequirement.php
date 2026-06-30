<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefaultRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'track',
        'group_name',
        'item_name',
        'source_document',
        'owner_type',
        'visibility',
        'soi_section',
        'gate_step',
        'is_required',
        'svf_only',
        'sort_order',
        'template_file_path',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'svf_only' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
