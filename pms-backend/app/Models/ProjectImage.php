<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'file_name',
        'file_path',
        'file_size',
        'file_type',
        'is_thumbnail',
        'sort_order',
        'uploaded_by',
        'uploaded_at',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_thumbnail' => 'boolean',
        'sort_order' => 'integer',
        'uploaded_at' => 'datetime',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }
}
