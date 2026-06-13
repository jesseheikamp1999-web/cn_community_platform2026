<?php

namespace App\Services;

use App\Models\AwardCategory;
use App\Models\AwardRound;
use App\Models\Nomination;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AwardService
{
    public function __construct(private readonly FraudDetectionService $fraudDetection)
    {
    }

    public function nominate(User $user, AwardCategory $category, array $data): Nomination
    {
        if (!$user->discord_id) {
            throw ValidationException::withMessages(['discord' => 'Koppel eerst je Discord-account.']);
        }
        if ($category->edition->status !== 'nominations' || !$category->is_active) {
            throw ValidationException::withMessages(['nomination' => 'Nomineren is voor deze categorie niet geopend.']);
        }
        $nominationRoundOpen = $category->edition->rounds()
            ->where('type', 'nomination')
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->exists();
        if (!$nominationRoundOpen) {
            throw ValidationException::withMessages(['nomination' => 'De nominatieronde is momenteel gesloten.']);
        }
        $defaultLimit = $category->edition->type === 'mini_awards' ? 1 : 5;
        $maxNominations = (int) ($category->edition->settings['nominations']['max_per_user'] ?? $defaultLimit);
        $userNominations = $category->nominations()
            ->where('user_id', $user->id)
            ->whereNull('canonical_nomination_id')
            ->count();
        if ($userNominations >= $maxNominations) {
            throw ValidationException::withMessages(['nomination' => 'Je mag maximaal '.$maxNominations.' nominaties per categorie insturen.']);
        }

        $normalizedName = mb_strtolower(trim(preg_replace('/\s+/', ' ', $data['nominee_name'])));
        $existingCandidate = $category->nominations()
            ->whereNull('canonical_nomination_id')
            ->whereRaw('LOWER(nominee_name) = ?', [$normalizedName])
            ->first();
        if ($existingCandidate) {
            $duplicate = $category->nominations()->create([
                'user_id' => $user->id,
                'nominee_name' => $data['nominee_name'],
                'nominee_discord_id' => $data['nominee_discord_id'] ?? null,
                'motivation' => $data['motivation'],
                'evidence_url' => $data['evidence_url'] ?? null,
                'evidence_text' => $data['evidence_text'] ?? null,
                'status' => 'duplicate',
                'canonical_nomination_id' => $existingCandidate->id,
                'spam_score' => $this->spamScore($data),
            ]);
            $existingCandidate->increment('duplicate_count');
            DB::table('nomination_review_logs')->insert([
                'nomination_id' => $existingCandidate->id,
                'user_id' => $user->id,
                'action' => 'merged_duplicate',
                'old_status' => $existingCandidate->status,
                'new_status' => $existingCandidate->status,
                'note' => 'Dubbele nominatie samengevoegd: '.$duplicate->nominee_name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $existingCandidate->fresh();
        }

        $alreadyNominated = $category->nominations()
            ->where('user_id', $user->id)
            ->whereRaw('LOWER(nominee_name) = ?', [$normalizedName])
            ->exists();
        if ($alreadyNominated) {
            throw ValidationException::withMessages(['nomination' => 'Je hebt deze kandidaat al in deze categorie genomineerd.']);
        }

        return $category->nominations()->create([
            'user_id' => $user->id,
            'nominee_name' => $data['nominee_name'],
            'nominee_discord_id' => $data['nominee_discord_id'] ?? null,
            'motivation' => $data['motivation'],
            'evidence_url' => $data['evidence_url'] ?? null,
            'evidence_text' => $data['evidence_text'] ?? null,
            'spam_score' => $this->spamScore($data),
        ]);
    }

    public function vote(User $user, Nomination $nomination, int $roundId, Request $request): Vote
    {
        if (!$user->discord_id) {
            throw ValidationException::withMessages(['discord' => 'Discord-login is verplicht om te stemmen.']);
        }
        if (!in_array($nomination->status, ['approved', 'finalist'], true)) {
            throw ValidationException::withMessages(['vote' => 'Op deze nominatie kan niet worden gestemd.']);
        }
        if (!$nomination->category->is_active) {
            throw ValidationException::withMessages(['vote' => 'Deze categorie is niet actief.']);
        }
        $round = AwardRound::find($roundId);
        if (
            !$round
            || $round->type !== 'public_vote'
            || $round->award_edition_id !== $nomination->category->award_edition_id
            || !$round->isOpen()
        ) {
            throw ValidationException::withMessages(['vote' => 'De stemronde is niet actief.']);
        }

        return DB::transaction(function () use ($user, $nomination, $roundId, $request) {
            $existingVote = Vote::where('user_id', $user->id)
                ->where('round_id', $roundId)
                ->whereHas('nomination', fn ($query) => $query->where('award_category_id', $nomination->award_category_id))
                ->whereNull('superseded_at')
                ->lockForUpdate()
                ->first();

            if ($existingVote?->nomination_id === $nomination->id) {
                return $existingVote;
            }
            $allowChange = (bool) ($nomination->category->edition->settings['voting']['allow_change'] ?? true);
            if ($existingVote && !$allowChange) {
                throw ValidationException::withMessages(['vote' => 'Je hebt in deze categorie al gestemd.']);
            }

            $score = $this->fraudDetection->score($user, $roundId, $request);
            $fingerprint = $this->fraudDetection->fingerprint($request);
            $browserFingerprint = hash('sha256', implode('|', [
                (string) $request->header('User-Agent'),
                (string) $request->header('Accept-Language'),
                (string) $request->header('Sec-CH-UA-Platform'),
            ]));
            if ($existingVote) {
                $existingVote->update(['superseded_at' => now(), 'is_valid' => false]);
            }

            $vote = Vote::create([
                'nomination_id' => $nomination->id,
                'user_id' => $user->id,
                'round_id' => $roundId,
                ...$fingerprint,
                'browser_fingerprint' => $browserFingerprint,
                'discord_account_age_days' => $user->created_at ? max(0, (int) $user->created_at->diffInDays(now())) : null,
                'fraud_score' => $score,
                'is_valid' => $score < 70,
            ]);
            DB::table('vote_histories')->insert([
                'round_id' => $roundId,
                'user_id' => $user->id,
                'award_category_id' => $nomination->award_category_id,
                'from_nomination_id' => $existingVote?->nomination_id,
                'to_nomination_id' => $nomination->id,
                ...$fingerprint,
                'browser_fingerprint' => $browserFingerprint,
                'fraud_score' => $score,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $vote;
        });
    }

    public function leaderboard(AwardCategory $category)
    {
        return $category->nominations()
            ->whereIn('status', ['approved', 'finalist', 'winner'])
            ->withCount(['votes' => fn ($query) => $query->where('is_valid', true)->whereNull('superseded_at')])
            ->orderByDesc('votes_count')
            ->get();
    }

    private function spamScore(array $data): float
    {
        $text = trim(($data['nominee_name'] ?? '').' '.($data['motivation'] ?? '').' '.($data['evidence_text'] ?? ''));
        $score = 0;
        if (mb_strlen($text) < 80) {
            $score += 25;
        }
        if (preg_match_all('/https?:\/\//i', $text) > 3) {
            $score += 20;
        }
        if (Str::of($text)->lower()->contains(['gratis nitro', 'spam', 'http://'])) {
            $score += 40;
        }

        return min(100, $score);
    }
}
