<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordSyncRequest extends Model
{
    protected $fillable = [
        'api_key_hint',
        'success',
        'item_count',
        'error_message',
        'requested_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'item_count' => 'integer',
            'requested_at' => 'datetime',
        ];
    }
}
