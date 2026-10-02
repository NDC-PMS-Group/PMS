<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskStatusHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'task_status_history';

    protected $fillable = [
        'task_id',
        'from_status',
        'to_status',
        'from_progress',
        'to_progress',
        'previous_due_date',
        'new_due_date',
        'actual_completion_date',
        'reason',
        'changed_by',
        'event_type',
        'notes',
        'changed_at',
    ];

    protected $casts = [
        'from_progress' => 'integer',
        'to_progress' => 'integer',
        'previous_due_date' => 'date',
        'new_due_date' => 'date',
        'actual_completion_date' => 'date',
        'changed_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
