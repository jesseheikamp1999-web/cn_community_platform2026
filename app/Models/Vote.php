<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    protected $fillable = [
        'nomination_id', 'user_id', 'round_id', 'ip_hash', 'user_agent_hash',
        'browser_fingerprint', 'discord_account_age_days', 'fraud_score', 'is_valid', 'superseded_at',
    ];

    protected function casts(): array
    {
        return ['fraud_score' => 'decimal:2', 'is_valid' => 'boolean', 'superseded_at' => 'datetime'];
    }

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(Nomination::class);
    }
}
