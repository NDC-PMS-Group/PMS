<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationEventSetting extends Model
{
    protected $fillable = [
        'event_key',
        'label',
        'category',
        'description',
        'in_app_enabled',
        'email_enabled',
        'template_name',
        'email_template_id',
        'updated_by',
    ];

    protected $casts = [
        'in_app_enabled' => 'boolean',
        'email_enabled' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }
}
