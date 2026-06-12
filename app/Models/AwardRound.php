<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AwardRound extends Model
{
    protected $fillable = ['award_edition_id', 'name', 'type', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(AwardEdition::class, 'award_edition_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class, 'round_id');
    }

    public function isOpen(): bool
    {
        return $this->is_active && $this->starts_at->lte(now()) && $this->ends_at->gte(now());
    }
}
