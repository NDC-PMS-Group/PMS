<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'profile_photo_url',
        'phone_number',
        'address',
        'employee_id',
        'department',
        'position',
        'date_hired',
        'birth_date',
        'default_role_id',
        'is_active',
        'last_login',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login' => 'datetime',
        'email_verified_at' => 'datetime',
        'date_hired' => 'date',
        'birth_date' => 'date',
    ];
    

    // Relationships
    public function defaultRole()
    {
        return $this->belongsTo(Role::class, 'default_role_id');
    }

    public function projectMemberships()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function createdProjects()
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function assignedProjects()
    {
        return $this->hasMany(Project::class, 'project_officer_id');
    }

    public function headedProjects()
    {
        return $this->hasMany(Project::class, 'workgroup_head_id');
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function savedReports()
    {
        return $this->hasMany(SavedReport::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    public function scopeByPosition($query, $position)
    {
        return $query->where('position', $position);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        $name = $this->first_name;
        
        if ($this->middle_name) {
            $name .= ' ' . $this->middle_name;
        }
        
        $name .= ' ' . $this->last_name;
        
        if ($this->suffix) {
            $name .= ' ' . $this->suffix;
        }
        
        return $name;
    }

    public function getInitialsAttribute()
    {
        $initials = strtoupper(substr($this->first_name, 0, 1));
        
        if ($this->middle_name) {
            $initials .= strtoupper(substr($this->middle_name, 0, 1));
        }
        
        $initials .= strtoupper(substr($this->last_name, 0, 1));
        
        return $initials;
    }

    public function getProfilePhotoAttribute()
    {
        if ($this->profile_photo_url) {
            return $this->profile_photo_url;
        }
        
        // Return default avatar URL or generate one
        return "https://ui-avatars.com/api/?name={$this->initials}&size=200&background=random";
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $roleName): bool
    {
        return $this->defaultRole && $this->defaultRole->name === $roleName;
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermissionTo(string $permissionName): bool
    {
        if (!$this->defaultRole) {
            return false;
        }

        return $this->defaultRole->permissions()
            ->where('name', $permissionName)
            ->exists();
    }
}