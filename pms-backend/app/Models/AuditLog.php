<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'email',
        'entity_type',
        'entity_id',
        'action',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'browser_version',
        'platform',
        'platform_version',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Accessors
    public function getActionTypeAttribute(): string
    {
        $actionMap = [
            'created' => 'create',
            'updated' => 'update',
            'deleted' => 'delete',
            'restored' => 'restore',
            'login' => 'login',
            'logout' => 'logout',
            'login_failed' => 'login_failed',
            'register' => 'register',
        ];

        return $actionMap[$this->action] ?? $this->action;
    }

    public function getModelTypeAttribute()
    {
        // Return entity_type (alias for frontend compatibility)
        return $this->entity_type;
    }

    public function getModelIdAttribute()
    {
        // Return entity_id (alias for frontend compatibility)
        return $this->entity_id;
    }

    public function getChangesAttribute()
    {
        // Combine old and new values for frontend
        if (!$this->old_values && !$this->new_values) {
            return null;
        }

        return [
            'old' => $this->old_values ?? [],
            'new' => $this->new_values ?? [],
        ];
    }

    public function getEmployeeAttribute()
    {
        // Return user relationship data formatted as "employee"
        if (!$this->user) {
            return null;
        }

        return [
            'first_name' => $this->user->first_name,
            'last_name' => $this->user->last_name,
        ];
    }

    // Scopes
    public function scopeForEntity($query, $entityType, $entityId)
    {
        return $query->where('entity_type', $entityType)
                     ->where('entity_id', $entityId);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('email', 'like', "%{$search}%")
              ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                  $userQuery->where('first_name', 'like', "%{$search}%")
                           ->orWhere('last_name', 'like', "%{$search}%")
                           ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$search}%"]);
              });
        });
    }

    public function scopeByActionType($query, $actionType)
    {
        if (empty($actionType) || $actionType === 'all') {
            return $query;
        }

        // Map frontend action types back to database values
        $dbAction = match($actionType) {
            'create' => 'created',
            'update' => 'updated',
            'delete' => 'deleted',
            default => $actionType // login, logout stay as-is
        };

        return $query->where('action', $dbAction);
    }

    public function scopeDateRange($query, $startDate = null, $endDate = null)
    {
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query;
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helper Methods
    public static function logActivity(array $data)
    {
        return static::create($data);
    }

    public static function logLogin($user, $request)
    {
        return static::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'action' => 'login',
            'description' => "{$user->full_name} logged in",
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }

    public static function logLogout($user, $request)
    {
        return static::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'action' => 'logout',
            'description' => "{$user->full_name} logged out",
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}