<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhilippineProvince extends Model
{
    protected $table = 'philippine_provinces';

    protected $fillable = [
        'psgc_code',
        'province_description',
        'region_code',
        'province_code',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(PhilippineRegion::class, 'region_code', 'region_code');
    }

    public function cities(): HasMany
    {
        return $this->hasMany(PhilippineCity::class, 'province_code', 'province_code');
    }
}
