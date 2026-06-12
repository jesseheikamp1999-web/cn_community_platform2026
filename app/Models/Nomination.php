<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nomination extends Model
{
    protected $fillable = [
        'award_category_id', 'user_id', 'nominee_name', 'nominee_discord_id', 'motivation',
        'evidence_url', 'evidence_text', 'logo_url', 'banner_url', 'website_url', 'discord_invite',
        'is_verified', 'status', 'canonical_nomination_id', 'duplicate_count',
        'spam_score', 'reviewed_by', 'reviewed_at', 'review_note', 'reputation_score',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'spam_score' => 'decimal:2', 'reputation_score' => 'decimal:2', 'is_verified' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AwardCategory::class, 'award_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function juryScores(): HasMany
    {
        return $this->hasMany(JuryScore::class);
    }

    public function canonical(): BelongsTo
    {
        return $this->belongsTo(self::class, 'canonical_nomination_id');
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'canonical_nomination_id');
    }
}
