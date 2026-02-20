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

    // Method to render template with data
    public function render(array $data = [])
    {
        $body = $this->body;
        $subject = $this->subject;
        
        foreach ($data as $key => $value) {
            $body = str_replace("{{" . $key . "}}", $value, $body);
            $subject = str_replace("{{" . $key . "}}", $value, $subject);
        }
        
        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}