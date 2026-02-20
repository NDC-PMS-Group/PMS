<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_code',
        'title',
        'description',
        'project_type_id',
        'industry_id',
        'sector_id',
        'investment_type_id',
        'funding_source_id',
        'estimated_cost',
        'actual_cost',
        'currency',
        'current_stage_id',
        'status_id',
        'proposal_date',
        'start_date',
        'target_completion_date',
        'actual_completion_date',
        'location_address',
        'location_lat',
        'location_lng',
        'map_layer',
        'thumbnail_url',
        'logo_url',
        'project_officer_id',
        'workgroup_head_id',
        'proponent_name',
        'proponent_contact',
        'proponent_email',
        'is_svf',
        'is_archived',
        'is_deleted',
        'created_by',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'location_lat' => 'decimal:8',
        'location_lng' => 'decimal:8',
        'proposal_date' => 'date',
        'start_date' => 'date',
        'target_completion_date' => 'date',
        'actual_completion_date' => 'date',
        'is_svf' => 'boolean',
        'is_archived' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    // Relationships
    public function projectType()
    {
        return $this->belongsTo(ProjectType::class);
    }

    public function industry()
    {
        return $this->belongsTo(Industry::class);
    }

    public function sector()
    {
        return $this->belongsTo(Sector::class);
    }

    public function investmentType()
    {
        return $this->belongsTo(InvestmentType::class);
    }

    public function fundingSource()
    {
        return $this->belongsTo(FundingSource::class);
    }

    public function currentStage()
    {
        return $this->belongsTo(ProjectStage::class, 'current_stage_id');
    }

    public function status()
    {
        return $this->belongsTo(ProjectStatus::class, 'status_id');
    }

    public function projectOfficer()
    {
        return $this->belongsTo(User::class, 'project_officer_id');
    }

    public function workgroupHead()
    {
        return $this->belongsTo(User::class, 'workgroup_head_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'project_tags');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function resources()
    {
        return $this->hasMany(ProjectResource::class);
    }

    public function stageHistory()
    {
        return $this->hasMany(ProjectStageHistory::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(ProjectStatusHistory::class);
    }

    public function approvals()
    {
        return $this->hasMany(ProjectApproval::class);
    }

    public function kpis()
    {
        return $this->hasMany(ProjectKpi::class);
    }

    public function svfApplication()
    {
        return $this->hasOne(SvfApplication::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false)
                     ->where('is_archived', false);
    }

    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    public function scopeSvf($query)
    {
        return $query->where('is_svf', true);
    }

    public function scopeByStage($query, $stageId)
    {
        return $query->where('current_stage_id', $stageId);
    }

    public function scopeByStatus($query, $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    // Mutators
    public function setProjectCodeAttribute($value)
    {
        $this->attributes['project_code'] = strtoupper($value);
    }

    // Accessors
    public function getIsOverdueAttribute()
    {
        if (!$this->target_completion_date) {
            return false;
        }
        return now()->gt($this->target_completion_date) && !$this->actual_completion_date;
    }

    public function getProgressPercentageAttribute()
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) {
            return 0;
        }
        $completedTasks = $this->tasks()->where('status', 'completed')->count();
        return round(($completedTasks / $totalTasks) * 100);
    }
}