<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectLegacyDetail extends Model
{
    use HasFactory;

    public const STATUS_NEEDS_DETAILS = 'needs_details';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETE = 'complete';

    protected $fillable = [
        'project_id',
        'legacy_import_batch_id',
        'source_row',
        'source_file',
        'source_status_raw',
        'source_cost_raw',
        'source_fund_released_raw',
        'source_partner_raw',
        'source_remarks',
        'row_fingerprint',
        'parse_warnings',
        'detail_status',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'parse_warnings' => 'array',
        'completed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function batch()
    {
        return $this->belongsTo(LegacyImportBatch::class, 'legacy_import_batch_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
