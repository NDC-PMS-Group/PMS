<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectMember extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'user_id',
        'role_id',
        'assignment_type',
        'can_view',
        'can_edit',
        'can_delete',
        'can_approve',
        'can_manage_members',
        'assigned_by',
        'assigned_at',
        'removed_at',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
        'can_approve' => 'boolean',
        'can_manage_members' => 'boolean',
        'assigned_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function customPermissions()
    {
        return $this->hasMany(ProjectMemberPermission::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNull('removed_at');
    }
}