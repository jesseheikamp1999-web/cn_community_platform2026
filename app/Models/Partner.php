<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $fillable = ['name', 'slug', 'website', 'discord_id', 'contact_name', 'contact_email', 'logo', 'status', 'tier', 'last_promotion_at', 'notes'];

    protected function casts(): array
    {
        return ['last_promotion_at' => 'datetime'];
    }

    public function getDestinationUrlAttribute(): ?string
    {
        if ($this->website && filter_var($this->website, FILTER_VALIDATE_URL)) {
            return $this->website;
        }

        if ($this->discord_id) {
            return str_starts_with($this->discord_id, 'http')
                ? $this->discord_id
                : 'https://discord.gg/'.$this->discord_id;
        }

        return null;
    }
}
