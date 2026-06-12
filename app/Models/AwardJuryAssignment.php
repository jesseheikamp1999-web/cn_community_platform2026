<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AwardJuryAssignment extends Model
{
    protected $fillable = ['award_edition_id', 'award_category_id', 'user_id', 'panel_name', 'is_chair'];

    protected function casts(): array
    {
        return ['is_chair' => 'boolean'];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(AwardEdition::class, 'award_edition_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AwardCategory::class, 'award_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
