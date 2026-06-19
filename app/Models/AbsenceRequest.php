<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class AbsenceRequest extends Model
{
    protected $fillable = [
        'user_id', 'starts_on', 'ends_on', 'starts_at', 'ends_at',
        'reason', 'status', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayEnd(): string
    {
        $end = $this->ends_at ?? $this->ends_on;

        return $end?->translatedFormat($this->ends_at ? 'd M H:i' : 'd M Y') ?? 'onbekend';
    }

    public function scopeCurrent(Builder $query): Builder
    {
        if (!Schema::hasColumns('absence_requests', ['starts_at', 'ends_at'])) {
            return $query
                ->where('status', 'approved')
                ->whereDate('starts_on', '<=', today())
                ->whereDate('ends_on', '>=', today());
        }

        return $query
            ->where('status', 'approved')
            ->where(function (Builder $period): void {
                $period
                    ->where(function (Builder $timed): void {
                        $timed->whereNotNull('starts_at')
                            ->whereNotNull('ends_at')
                            ->where('starts_at', '<=', now())
                            ->where('ends_at', '>=', now());
                    })
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('starts_at')
                            ->whereDate('starts_on', '<=', today())
                            ->whereDate('ends_on', '>=', today());
                    });
            });
    }
}
