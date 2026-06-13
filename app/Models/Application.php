<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'position',
        'answers',
        'status',
        'internal_note',
        'reviewed_by',
        'reviewed_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'reviewed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
