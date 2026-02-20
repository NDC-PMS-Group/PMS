<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectStageHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'project_stage_history';

    protected $fillable = [
        'project_id',
        'from_stage_id',
        'to_stage_id',
        'changed_by',
        'change_reason',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function fromStage()
    {
        return $this->belongsTo(ProjectStage::class, 'from_stage_id');
    }

    public function toStage()
    {
        return $this->belongsTo(ProjectStage::class, 'to_stage_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}