<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhilippineCity extends Model
{
    protected $table = 'philippine_cities';

    protected $fillable = [
        'psgc_code',
        'city_municipality_description',
        'region_description',
        'province_code',
        'city_municipality_code',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(PhilippineProvince::class, 'province_code', 'province_code');
    }

    public function barangays(): HasMany
    {
        return $this->hasMany(PhilippineBarangay::class, 'city_municipality_code', 'city_municipality_code');
    }
}
