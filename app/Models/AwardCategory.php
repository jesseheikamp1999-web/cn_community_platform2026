<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AwardCategory extends Model
{
    protected $fillable = ['award_edition_id', 'name', 'slug', 'description', 'icon', 'sort_order', 'jury_weight', 'public_weight', 'is_active'];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(AwardEdition::class, 'award_edition_id');
    }

    public function nominations(): HasMany
    {
        return $this->hasMany(Nomination::class);
    }
}
