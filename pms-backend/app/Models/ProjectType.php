<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectType extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['name', 'description'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function approvalWorkflows()
    {
        return $this->hasMany(ApprovalWorkflow::class);
    }
}