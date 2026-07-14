<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    public const SOI_SECTIONS = [
        'intake',
        'requirements',
        'due_diligence',
        'management_review',
        'board_approval',
        'agreement_fund_release',
        'implementation_monitoring',
        'post_investment_strategy',
        'divestment',
        'completion',
    ];

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'task_type',
        'soi_section',
        'task_scope',
        'workstream',
        'template_source',
        'archived_at',
        'archive_reason',
        'assigned_to',
        'assigned_by',
        'start_date',
        'due_date',
        'completion_date',
        'status',
        'progress_percentage',
        'priority',
        'estimated_hours',
        'actual_hours',
        'parent_task_id',
        'is_milestone',
        'is_deleted',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'completion_date' => 'date',
        'progress_percentage' => 'integer',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'is_milestone' => 'boolean',
        'is_deleted' => 'boolean',
        'archived_at' => 'datetime',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function parentTask()
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subtasks()
    {
        return $this->hasMany(Task::class, 'parent_task_id')
            ->where('is_deleted', false);
    }

    public function dependencies()
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id')
                    ->withPivot('dependency_type');
    }

    public function dependents()
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_task_id', 'task_id')
                    ->withPivot('dependency_type');
    }

    public function resources()
    {
        return $this->hasMany(TaskResource::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(TaskStatusHistory::class)
            ->with('changedBy')
            ->orderByDesc('changed_at');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false)
            ->whereNull('archived_at');
    }

    public function scopeImplementation($query)
    {
        return $query->where('task_scope', 'implementation');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                     ->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeMilestones($query)
    {
        return $query->where('is_milestone', true);
    }

    // Accessors
    public function getIsOverdueAttribute()
    {
        if (!$this->due_date || in_array($this->status, ['completed', 'cancelled'])) {
            return false;
        }
        return now()->gt($this->due_date);
    }

    public static function deriveSoiSection(?string $taskType, ?string $title = null): ?string
    {
        $type = strtolower((string) $taskType);
        $text = strtolower(trim($type . ' ' . (string) $title));

        if (str_contains($text, 'divest')) return 'divestment';
        if (str_contains($text, 'post-investment') || str_contains($text, 'post investment')) return 'post_investment_strategy';
        if (str_contains($text, 'monitor')) return 'implementation_monitoring';
        if (str_contains($text, 'fund') || str_contains($text, 'agreement') || str_contains($text, 'jva') || str_contains($text, 'construction')) return 'agreement_fund_release';
        if (str_contains($text, 'board')) return 'board_approval';
        if (str_contains($text, 'mancom') || str_contains($text, 'workgroup') || $type === 'approval') return 'management_review';
        if (str_contains($text, 'diligence') || str_contains($text, 'evaluation') || str_contains($text, 'study') || $type === 'due_diligence') return 'due_diligence';
        if (str_contains($text, 'requirement') || str_contains($text, 'completeness')) return 'requirements';
        if (str_contains($text, 'completion') || str_contains($text, 'turn-over') || str_contains($text, 'turnover')) return 'completion';
        if (str_contains($text, 'intake') || str_contains($text, 'concept') || str_contains($text, 'submission') || $type === 'intake') return 'intake';

        return in_array($type, self::SOI_SECTIONS, true) ? $type : null;
    }
}
