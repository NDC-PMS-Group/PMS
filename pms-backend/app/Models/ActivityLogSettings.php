<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class ActivityLogSettings extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'retention_months',
        'max_id',
        'auto_cleanup_enabled',
        'last_cleanup_at',
    ];

    protected $casts = [
        'retention_months' => 'integer',
        'max_id' => 'integer',
        'auto_cleanup_enabled' => 'boolean',
        'last_cleanup_at' => 'datetime',
    ];

    /**
     * Fields to audit (only track meaningful changes)
     */
    protected $auditableFields = [
        'retention_months',
        'max_id',
        'auto_cleanup_enabled',
    ];

    /**
     * Get the singleton settings instance
     * Always returns the first (and only) record
     */
    public static function getSettings()
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'retention_months' => 3,
                'max_id' => 1000000,
                'auto_cleanup_enabled' => true,
            ]
        );
    }

    /**
     * Update the settings
     */
    public static function updateSettings(array $data)
    {
        $settings = static::getSettings();
        $settings->update($data);
        return $settings->fresh();
    }

    /**
     * Get retention cutoff date
     * Logs older than this date should be deleted
     */
    public function getRetentionCutoffDate()
    {
        return now()->subMonths($this->retention_months);
    }

    /**
     * Check if automatic cleanup is due
     * Returns true if auto cleanup is enabled and hasn't run in the last 24 hours
     */
    public function isCleanupDue()
    {
        if (!$this->auto_cleanup_enabled) {
            return false;
        }

        if (!$this->last_cleanup_at) {
            return true;
        }

        return $this->last_cleanup_at->lt(now()->subDay());
    }

    /**
     * Mark cleanup as completed
     */
    public function markCleanupCompleted()
    {
        $this->update(['last_cleanup_at' => now()]);
    }

    /**
     * Get the number of logs that would be deleted
     */
    public function getLogsToDeleteCount()
    {
        $cutoffDate = $this->getRetentionCutoffDate();
        return AuditLog::where('created_at', '<', $cutoffDate)->count();
    }

    /**
     * Perform cleanup of old logs
     * Returns the number of deleted records
     */
    public function performCleanup()
    {
        $cutoffDate = $this->getRetentionCutoffDate();
        
        $deletedCount = AuditLog::where('created_at', '<', $cutoffDate)->delete();
        
        $this->markCleanupCompleted();
        
        return $deletedCount;
    }

    /**
     * Custom audit description for better readability
     */
    public function getCustomAuditDescription(string $action): string
    {
        $user = auth()->user();
        $userName = $user ? $user->full_name : 'System';

        switch ($action) {
            case 'created':
                return "{$userName} initialized Activity Log Settings";
            case 'updated':
                return "{$userName} updated Activity Log Settings";
            case 'deleted':
                return "{$userName} deleted Activity Log Settings";
            default:
                return "{$userName} {$action} Activity Log Settings";
        }
    }

    /**
     * Skip audit for last_cleanup_at updates (too frequent)
     */
    public function shouldSkipAuditChanges(array $changes): bool
    {
        // Skip if only last_cleanup_at changed (this happens automatically during cleanup)
        if (count($changes) === 1 && isset($changes['last_cleanup_at'])) {
            return true;
        }

        return false;
    }

    /**
     * Boot method to enforce singleton pattern
     */
    protected static function boot()
    {
        parent::boot();

        // Prevent creating more than one settings record
        static::creating(function ($model) {
            if (static::count() > 0 && $model->id !== 1) {
                throw new \Exception('Only one ActivityLogSettings record is allowed');
            }
        });
    }
}