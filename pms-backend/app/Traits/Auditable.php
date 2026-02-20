<?php

namespace App\Traits;

use App\Models\AuditLog;
use App\Services\UserAgentParserService;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Boot the auditable trait for a model.
     */
    public static function bootAuditable(): void
    {
        // Log when a model is created
        static::created(function ($model) {
            $model->auditCreated();
        });

        // Log when a model is updated
        static::updated(function ($model) {
            $model->auditUpdated();
        });

        // Log when a model is deleted
        static::deleted(function ($model) {
            $model->auditDeleted();
        });

        // Log when a soft-deleted model is restored
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                $model->auditRestored();
            });
        }
    }

    /**
     * Log model creation
     */
    protected function auditCreated(): void
    {
        try {
            $user = Auth::user();
            $agentData = $this->getAgentData();

            $newValues = $this->getAuditableAttributes();

            AuditLog::create([
                'user_id' => $user?->id,
                'email' => $user?->email ?? 'system',
                'entity_type' => get_class($this),
                'entity_id' => $this->getKey(),
                'action' => 'created',
                'description' => $this->getAuditDescription('created'),
                'old_values' => null,
                'new_values' => json_encode($newValues),
                'ip_address' => $agentData['ip_address'],
                'user_agent' => $agentData['user_agent'],
                'device_type' => $agentData['device_type'],
                'browser' => $agentData['browser'],
                'browser_version' => $agentData['browser_version'],
                'platform' => $agentData['platform'],
                'platform_version' => $agentData['platform_version'],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to audit model creation: ' . $e->getMessage());
        }
    }

    /**
     * Log model update
     */
    protected function auditUpdated(): void
    {
        try {
            $user = Auth::user();
            $agentData = $this->getAgentData();

            // Get the changes (only modified attributes)
            $changes = $this->getChanges();
            
            // Skip if no meaningful changes
            if (empty($changes) || $this->shouldSkipAudit($changes)) {
                return;
            }

            $oldValues = [];
            $newValues = [];

            foreach ($changes as $key => $newValue) {
                if ($this->shouldAuditAttribute($key)) {
                    $oldValues[$key] = $this->getOriginal($key);
                    $newValues[$key] = $newValue;
                }
            }

            // Only log if there are actual changes to auditable fields
            if (empty($oldValues) && empty($newValues)) {
                return;
            }

            AuditLog::create([
                'user_id' => $user?->id,
                'email' => $user?->email ?? 'system',
                'entity_type' => get_class($this),
                'entity_id' => $this->getKey(),
                'action' => 'updated',
                'description' => $this->getAuditDescription('updated'),
                'old_values' => json_encode($oldValues),
                'new_values' => json_encode($newValues),
                'ip_address' => $agentData['ip_address'],
                'user_agent' => $agentData['user_agent'],
                'device_type' => $agentData['device_type'],
                'browser' => $agentData['browser'],
                'browser_version' => $agentData['browser_version'],
                'platform' => $agentData['platform'],
                'platform_version' => $agentData['platform_version'],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to audit model update: ' . $e->getMessage());
        }
    }

    /**
     * Log model deletion
     */
    protected function auditDeleted(): void
    {
        try {
            $user = Auth::user();
            $agentData = $this->getAgentData();

            $oldValues = $this->getAuditableAttributes();

            AuditLog::create([
                'user_id' => $user?->id,
                'email' => $user?->email ?? 'system',
                'entity_type' => get_class($this),
                'entity_id' => $this->getKey(),
                'action' => 'deleted',
                'description' => $this->getAuditDescription('deleted'),
                'old_values' => json_encode($oldValues),
                'new_values' => null,
                'ip_address' => $agentData['ip_address'],
                'user_agent' => $agentData['user_agent'],
                'device_type' => $agentData['device_type'],
                'browser' => $agentData['browser'],
                'browser_version' => $agentData['browser_version'],
                'platform' => $agentData['platform'],
                'platform_version' => $agentData['platform_version'],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to audit model deletion: ' . $e->getMessage());
        }
    }

    /**
     * Log model restoration (from soft delete)
     */
    protected function auditRestored(): void
    {
        try {
            $user = Auth::user();
            $agentData = $this->getAgentData();

            $newValues = $this->getAuditableAttributes();

            AuditLog::create([
                'user_id' => $user?->id,
                'email' => $user?->email ?? 'system',
                'entity_type' => get_class($this),
                'entity_id' => $this->getKey(),
                'action' => 'restored',
                'description' => $this->getAuditDescription('restored'),
                'old_values' => null,
                'new_values' => json_encode($newValues),
                'ip_address' => $agentData['ip_address'],
                'user_agent' => $agentData['user_agent'],
                'device_type' => $agentData['device_type'],
                'browser' => $agentData['browser'],
                'browser_version' => $agentData['browser_version'],
                'platform' => $agentData['platform'],
                'platform_version' => $agentData['platform_version'],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to audit model restoration: ' . $e->getMessage());
        }
    }

    /**
     * Get user agent data from current request
     */
    protected function getAgentData(): array
    {
        try {
            $request = request();
            
            if (!$request) {
                return $this->getDefaultAgentData();
            }

            $parser = app(UserAgentParserService::class);
            return $parser->parse($request);
        } catch (\Exception $e) {
            return $this->getDefaultAgentData();
        }
    }

    /**
     * Get default agent data when request is not available
     */
    protected function getDefaultAgentData(): array
    {
        return [
            'ip_address' => '127.0.0.1',
            'user_agent' => 'System',
            'device_type' => 'Server',
            'browser' => null,
            'browser_version' => null,
            'platform' => 'CLI',
            'platform_version' => null,
        ];
    }

    /**
     * Get attributes that should be audited
     */
    protected function getAuditableAttributes(): array
    {
        $attributes = $this->getAttributes();

        // If model defines specific auditable fields, use those
        if (property_exists($this, 'auditableFields') && !empty($this->auditableFields)) {
            return array_intersect_key($attributes, array_flip($this->auditableFields));
        }

        // Otherwise, exclude common timestamps and system fields
        $excludeFields = $this->getAuditExcludedFields();
        
        return array_diff_key($attributes, array_flip($excludeFields));
    }

    /**
     * Check if an attribute should be audited
     */
    protected function shouldAuditAttribute(string $attribute): bool
    {
        // If model defines specific auditable fields, check against that
        if (property_exists($this, 'auditableFields') && !empty($this->auditableFields)) {
            return in_array($attribute, $this->auditableFields);
        }

        // Otherwise, exclude common fields
        return !in_array($attribute, $this->getAuditExcludedFields());
    }

    /**
     * Get fields that should be excluded from auditing
     */
    protected function getAuditExcludedFields(): array
    {
        $defaultExcluded = [
            'created_at',
            'updated_at',
            'deleted_at',
            'remember_token',
            'password',
            'password_hash',
            'email_verified_at',
        ];

        // Allow models to define additional excluded fields
        if (property_exists($this, 'auditExcludedFields')) {
            return array_merge($defaultExcluded, $this->auditExcludedFields);
        }

        return $defaultExcluded;
    }

    /**
     * Check if audit should be skipped for these changes
     */
    protected function shouldSkipAudit(array $changes): bool
    {
        // Skip if only updated_at changed
        if (count($changes) === 1 && isset($changes['updated_at'])) {
            return true;
        }

        // Allow models to define custom skip logic
        if (method_exists($this, 'shouldSkipAuditChanges')) {
            return $this->shouldSkipAuditChanges($changes);
        }

        return false;
    }

    /**
     * Get audit description
     */
    protected function getAuditDescription(string $action): string
    {
        $user = Auth::user();
        $userName = $user ? $user->full_name : 'System';
        $modelName = class_basename($this);
        $modelIdentifier = $this->getAuditIdentifier();

        $descriptions = [
            'created' => "{$userName} created {$modelName} {$modelIdentifier}",
            'updated' => "{$userName} updated {$modelName} {$modelIdentifier}",
            'deleted' => "{$userName} deleted {$modelName} {$modelIdentifier}",
            'restored' => "{$userName} restored {$modelName} {$modelIdentifier}",
        ];

        // Allow models to override description
        if (method_exists($this, 'getCustomAuditDescription')) {
            return $this->getCustomAuditDescription($action);
        }

        return $descriptions[$action] ?? "{$userName} {$action} {$modelName}";
    }

    /**
     * Get model identifier for audit description
     */
    protected function getAuditIdentifier(): string
    {
        // Try common identifier fields
        $identifierFields = ['name', 'title', 'username', 'email', 'code'];

        foreach ($identifierFields as $field) {
            if (isset($this->{$field})) {
                return "'{$this->{$field}}'";
            }
        }

        // Fallback to primary key
        return "#{$this->getKey()}";
    }
}