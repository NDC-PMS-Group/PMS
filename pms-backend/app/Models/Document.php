<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'task_id',
        'title',
        'description',
        'file_name',
        'file_path',
        'file_size',
        'file_type',
        'category',
        'version',
        'is_public',
        'requires_approval',
        'uploaded_by',
        'uploaded_at',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'version' => 'integer',
        'is_public' => 'boolean',
        'requires_approval' => 'boolean',
        'is_deleted' => 'boolean',
        'uploaded_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}