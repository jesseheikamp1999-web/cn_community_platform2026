<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DiscordMember extends Model
{
    protected $fillable = [
        'discord_id',
        'username',
        'display_name',
        'avatar',
        'platform_role',
        'roles',
        'joined_at',
        'is_bot',
        'is_active',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'joined_at' => 'datetime',
            'is_bot' => 'boolean',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function mijncnUser(): HasOne
    {
        return $this->hasOne(User::class, 'discord_id', 'discord_id');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        $extension = str_starts_with($this->avatar, 'a_') ? 'gif' : 'png';

        return "https://cdn.discordapp.com/avatars/{$this->discord_id}/{$this->avatar}.{$extension}?size=256";
    }
}
