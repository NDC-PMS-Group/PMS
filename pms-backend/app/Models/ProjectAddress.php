<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'house_number',
        'floor',
        'street',
        'barangay',
        'city_municipality',
        'province',
        'region',
        'country',
        'zip_code',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude'  => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    /**
     * Comma-joined full address. Used by the controller to derive the legacy
     * projects.location_address column on save.
     */
    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->house_number,
            $this->floor ? "Floor {$this->floor}" : null,
            $this->street,
            $this->barangay,
            $this->city_municipality,
            $this->province,
            $this->region,
            $this->country,
            $this->zip_code,
        ])->filter()->implode(', ');
    }
}
