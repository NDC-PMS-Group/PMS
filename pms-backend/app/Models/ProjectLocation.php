<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectLocation extends Model
{
    protected $fillable = ['region_code', 'region_name', 'province_code', 'province_name', 'address'];
}
