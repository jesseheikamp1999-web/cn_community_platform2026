<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JuryScore extends Model
{
    protected $fillable = [
        'nomination_id', 'jury_id', 'score', 'impact_score', 'activity_score',
        'professionalism_score', 'innovation_score', 'future_score', 'originality_score',
        'design_score', 'community_score', 'strengths', 'improvements', 'personal_note', 'report',
    ];

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(Nomination::class);
    }

    public function jury(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jury_id');
    }
}
