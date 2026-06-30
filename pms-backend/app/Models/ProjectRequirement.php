<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'document_id',
        'received_by',
        'group_name',
        'item_name',
        'source_document',
        'track',
        'owner_type',
        'visibility',
        'soi_section',
        'gate_step',
        'is_required',
        'is_applicable',
        'svf_only',
        'status',
        'due_date',
        'received_at',
        'remarks',
        'sort_order',
        'template_file_path',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_applicable' => 'boolean',
        'svf_only' => 'boolean',
        'due_date' => 'date',
        'received_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
