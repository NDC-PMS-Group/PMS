<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SvfApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'application_number',
        'startup_name',
        'startup_description',
        'founder_name',
        'founder_email',
        'founder_phone',
        'requested_amount',
        'evaluation_score',
        'submitted_via',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'evaluation_score' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function evaluations()
    {
        return $this->hasMany(SvfEvaluation::class);
    }

    // Accessors
    public function getAverageScoreAttribute()
    {
        return $this->evaluations()->avg('score');
    }
}