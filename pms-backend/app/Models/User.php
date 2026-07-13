<?php

namespace App\Models;

use App\Notifications\QueuedVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

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
        'organization_name',
        'organization_type',
        'organization_registration_no',
        'proponent_profile',
        'employee_id',
        'department',
        'position',
        'date_hired',
        'birth_date',
        'default_role_id',
        'is_active',
        'last_login',
        'email_verified_at',
        'staff_invitation_token',
        'staff_invitation_expires_at',
        'staff_invitation_accepted_at',
        'invited_by_id',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
        'staff_invitation_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login' => 'datetime',
        'email_verified_at' => 'datetime',
        'date_hired' => 'date',
        'birth_date' => 'date',
        'proponent_profile' => 'array',
        'staff_invitation_expires_at' => 'datetime',
        'staff_invitation_accepted_at' => 'datetime',
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

    public function notificationPreferences()
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function registrationDocuments()
    {
        return $this->hasMany(ProponentRegistrationDocument::class);
    }

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    public function savedReports()
    {
        return $this->hasMany(SavedReport::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function previousProjects()
    {
        return $this->hasMany(UserPreviousProject::class)->orderBy('start_date', 'desc');
    }

    public function receivedInvitations()
    {
        return $this->hasMany(ProjectInvitation::class, 'email', 'email');
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by_id');
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
        return $this->defaultRole && strcasecmp($this->defaultRole->name, $roleName) === 0;
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
