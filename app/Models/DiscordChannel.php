<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscordChannel extends Model
{
    protected $fillable = [
        'discord_channel_id',
        'name',
        'purpose',
        'webhook_url',
        'static_message_id',
        'static_message_updated_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'static_message_updated_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(DiscordDelivery::class);
    }
}
