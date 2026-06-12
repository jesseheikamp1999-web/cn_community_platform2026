<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AwardEdition extends Model
{
    protected $fillable = ['name', 'slug', 'type', 'year', 'status', 'starts_at', 'ends_at', 'finale_at', 'settings'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'finale_at' => 'datetime', 'settings' => 'array'];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(AwardCategory::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(AwardRound::class);
    }

    public function juryAssignments(): HasMany
    {
        return $this->hasMany(AwardJuryAssignment::class);
    }
}
