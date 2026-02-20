<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectResource extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'resource_id',
        'quantity',
        'allocated_amount',
        'used_amount',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'allocated_amount' => 'decimal:2',
        'used_amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    // Accessors
    public function getRemainingAmountAttribute()
    {
        return $this->allocated_amount - $this->used_amount;
    }

    public function getUtilizationPercentageAttribute()
    {
        if (!$this->allocated_amount) {
            return 0;
        }
        return round(($this->used_amount / $this->allocated_amount) * 100, 2);
    }
}