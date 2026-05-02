<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhilippineBarangay extends Model
{
    protected $table = 'philippine_barangays';

    protected $fillable = [
        'barangay_code',
        'barangay_description',
        'region_code',
        'province_code',
        'city_municipality_code',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(PhilippineCity::class, 'city_municipality_code', 'city_municipality_code');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(PhilippineProvince::class, 'province_code', 'province_code');
    }
}
