<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SvfEvaluationCriteria extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'svf_evaluation_criteria';

    protected $fillable = [
        'name',
        'description',
        'max_score',
        'weight',
        'is_active',
    ];

    protected $casts = [
        'max_score' => 'integer',
        'weight' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function evaluations()
    {
        return $this->hasMany(SvfEvaluation::class, 'criteria_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}