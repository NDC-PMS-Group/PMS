<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectMemberPermission extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'project_member_id',
        'permission_id',
        'granted',
        'granted_by',
        'granted_at',
    ];

    protected $casts = [
        'granted' => 'boolean',
        'granted_at' => 'datetime',
    ];

    public function projectMember()
    {
        return $this->belongsTo(ProjectMember::class);
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}