<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplateVersion extends Model
{
    protected $fillable = [
        'email_template_id',
        'version',
        'status',
        'subject',
        'body',
        'variables',
        'created_by',
        'published_by',
        'restored_from_id',
        'published_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'published_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
