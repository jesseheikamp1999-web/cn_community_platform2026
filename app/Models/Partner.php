<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Partner extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'website', 'discord_id', 'contact_name',
        'contact_email', 'logo', 'status', 'tier', 'category', 'score',
        'position', 'is_featured', 'last_promotion_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'last_promotion_at' => 'datetime',
            'is_featured' => 'boolean',
            'score' => 'integer',
            'position' => 'integer',
        ];
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

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        if (str_starts_with($this->logo, 'http://') || str_starts_with($this->logo, 'https://')) {
            return $this->logo;
        }

        return Storage::disk('public')->url($this->logo);
    }
}
