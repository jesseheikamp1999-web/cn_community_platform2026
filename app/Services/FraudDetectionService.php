<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vote;
use Illuminate\Http\Request;

class FraudDetectionService
{
    public function fingerprint(Request $request): array
    {
        $key = config('app.key', 'cn-community');

        return [
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), $key),
            'user_agent_hash' => hash_hmac('sha256', (string) $request->userAgent(), $key),
        ];
    }

    public function score(User $user, int $roundId, Request $request): float
    {
        $fingerprint = $this->fingerprint($request);
        $score = 0;

        if (!$user->discord_id) {
            $score += 100;
        }

        $sameIpVotes = Vote::query()
            ->where('round_id', $roundId)
            ->where('ip_hash', $fingerprint['ip_hash'])
            ->distinct('user_id')
            ->count('user_id');

        if ($sameIpVotes >= 3) {
            $score += min(60, ($sameIpVotes - 2) * 15);
        }

        if ($user->created_at?->greaterThan(now()->subDay())) {
            $score += 20;
        }

        return min(100, $score);
    }
}
