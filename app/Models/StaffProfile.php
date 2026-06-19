<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffProfile extends Model
{
    protected $fillable = [
        'user_id', 'position', 'status', 'public_status', 'joined_at', 'bio',
        'specialties', 'discord_url', 'is_team_member_of_month',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'specialties' => 'array',
            'is_team_member_of_month' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
