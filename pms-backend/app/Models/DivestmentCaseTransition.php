<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DivestmentCaseTransition extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'divestment_case_id',
        'from_phase',
        'to_phase',
        'notes',
        'transitioned_by',
        'transitioned_at',
    ];

    protected $casts = ['transitioned_at' => 'datetime'];

    public function divestmentCase()
    {
        return $this->belongsTo(DivestmentCase::class);
    }

    public function transitionedBy()
    {
        return $this->belongsTo(User::class, 'transitioned_by');
    }
}
