<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectKpi extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'kpi_definition_id',
        'actual_value',
        'recorded_at',
        'notes',
    ];

    protected $casts = [
        'actual_value' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function kpiDefinition()
    {
        return $this->belongsTo(KpiDefinition::class);
    }

    // Accessors
    public function getVarianceAttribute()
    {
        if (!$this->kpiDefinition || !$this->kpiDefinition->target_value) {
            return null;
        }
        return $this->actual_value - $this->kpiDefinition->target_value;
    }

    public function getAchievementPercentageAttribute()
    {
        if (!$this->kpiDefinition || !$this->kpiDefinition->target_value) {
            return null;
        }
        return round(($this->actual_value / $this->kpiDefinition->target_value) * 100, 2);
    }
}