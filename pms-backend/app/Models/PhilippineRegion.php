<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhilippineRegion extends Model
{
    protected $table = 'philippine_regions';

    protected $fillable = [
        'psgc_code',
        'region_description',
        'region_code',
    ];

    public function provinces(): HasMany
    {
        return $this->hasMany(PhilippineProvince::class, 'region_code', 'region_code');
    }
}
