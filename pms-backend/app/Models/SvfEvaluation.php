<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SvfEvaluation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'svf_application_id',
        'criteria_id',
        'evaluator_id',
        'score',
        'comments',
        'evaluated_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'evaluated_at' => 'datetime',
    ];

    public function svfApplication()
    {
        return $this->belongsTo(SvfApplication::class);
    }

    public function criteria()
    {
        return $this->belongsTo(SvfEvaluationCriteria::class, 'criteria_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    // Accessors
    public function getWeightedScoreAttribute()
    {
        if (!$this->criteria) {
            return 0;
        }
        return $this->score * $this->criteria->weight;
    }
}