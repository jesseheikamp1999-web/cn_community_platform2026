<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordSyncRequest extends Model
{
    protected $fillable = [
        'api_key_hint',
        'channel_key',
        'success',
        'status_code',
        'item_count',
        'error_message',
        'ip_address',
        'user_agent',
        'requested_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'status_code' => 'integer',
            'item_count' => 'integer',
            'requested_at' => 'datetime',
        ];
    }
}
