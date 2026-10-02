<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegacyImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'imported_by',
        'total_rows',
        'created_count',
        'skipped_count',
        'status_summary',
        'warnings',
    ];

    protected $casts = [
        'status_summary' => 'array',
        'warnings' => 'array',
    ];

    public function importedBy()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function legacyDetails()
    {
        return $this->hasMany(ProjectLegacyDetail::class);
    }
}
