<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordDelivery extends Model
{
    protected $fillable = [
        'discord_channel_id',
        'event',
        'payload',
        'status',
        'response',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(DiscordChannel::class, 'discord_channel_id');
    }
}
