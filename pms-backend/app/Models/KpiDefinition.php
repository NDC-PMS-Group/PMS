<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiDefinition extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'calculation_formula',
        'unit',
        'target_value',
        'is_active',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function projectKpis()
    {
        return $this->hasMany(ProjectKpi::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}