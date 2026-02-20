<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrashBin extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'trash_bin';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'entity_data',
        'deleted_by',
        'deleted_at',
        'can_restore_until',
    ];

    protected $casts = [
        'entity_data' => 'array',
        'deleted_at' => 'datetime',
        'can_restore_until' => 'datetime',
    ];

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // Scopes
    public function scopeRestorable($query)
    {
        return $query->where('can_restore_until', '>', now())
                     ->orWhereNull('can_restore_until');
    }

    public function scopeExpired($query)
    {
        return $query->where('can_restore_until', '<=', now());
    }
}