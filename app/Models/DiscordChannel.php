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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(DiscordDelivery::class);
    }
}
