<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordSyncPanel extends Model
{
    protected $fillable = [
        'key',
        'title',
        'description',
        'button_label',
        'button_url',
        'secondary_button_label',
        'secondary_button_url',
        'refresh_after_seconds',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'refresh_after_seconds' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
