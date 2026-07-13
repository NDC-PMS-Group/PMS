<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $fillable = [
        'notification_id',
        'user_id',
        'template_version_id',
        'event_key',
        'channel',
        'recipient_address',
        'subject',
        'payload',
        'status',
        'is_test',
        'attempts',
        'failure_reason',
        'context',
        'queued_at',
        'sent_at',
        'failed_at',
    ];

    protected $hidden = ['recipient_address', 'payload', 'context'];

    protected $casts = [
        'recipient_address' => 'encrypted',
        'payload' => 'encrypted',
        'context' => 'array',
        'is_test' => 'boolean',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function templateVersion()
    {
        return $this->belongsTo(NotificationTemplateVersion::class, 'template_version_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
