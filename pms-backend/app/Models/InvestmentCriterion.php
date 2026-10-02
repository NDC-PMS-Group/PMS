<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentCriterion extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
