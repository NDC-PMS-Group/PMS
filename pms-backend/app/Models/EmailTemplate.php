<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subject',
        'body',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function versions()
    {
        return $this->hasMany(NotificationTemplateVersion::class)->orderByDesc('version');
    }

    public function publishedVersions()
    {
        return $this->versions()->where('status', 'published');
    }

    public function eventSettings()
    {
        return $this->hasMany(NotificationEventSetting::class, 'template_name', 'name');
    }

    public function draftVersion()
    {
        return $this->hasOne(NotificationTemplateVersion::class)->where('status', 'draft')->latestOfMany('version');
    }

    public function latestPublishedVersion()
    {
        return $this->hasOne(NotificationTemplateVersion::class)->where('status', 'published')->latestOfMany('version');
    }

    // Method to render template with data
    public function render(array $data = [])
    {
        $published = $this->relationLoaded('latestPublishedVersion')
            ? $this->latestPublishedVersion
            : $this->latestPublishedVersion()->first();
        $body = $published?->body ?? $this->body;
        $subject = $published?->subject ?? $this->subject;

        foreach ($data as $key => $value) {
            $body = str_replace('{{'.$key.'}}', $value, $body);
            $subject = str_replace('{{'.$key.'}}', $value, $subject);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
