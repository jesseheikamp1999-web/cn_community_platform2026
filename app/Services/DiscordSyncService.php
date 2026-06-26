<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use App\Models\AwardEdition;
use App\Models\Content;
use App\Models\DiscordChannel;
use App\Models\DiscordSyncRequest;
use App\Models\Nomination;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DiscordSyncService
{
    public function items(): array
    {
        return collect()
            ->merge($this->panelItems())
            ->merge($this->newsItems())
            ->merge($this->birthdayItems())
            ->merge($this->absenceItems())
            ->values()
            ->all();
    }

    public function panelKeys(): array
    {
        return ['cn-pulse', 'staff-status', 'awards-info', 'stem-nu', 'trending', 'leaderboard', 'award-logs'];
    }

    public function activePanels(): Collection
    {
        if (!Schema::hasTable('discord_channels')) {
            return collect($this->panelKeys())->map(fn (string $key) => [
                'key' => $key,
                'name' => $key,
                'active' => true,
                'message_id' => null,
                'updated_at' => null,
            ]);
        }

        return DiscordChannel::whereIn('purpose', $this->panelKeys())
            ->orderBy('purpose')
            ->get()
            ->map(fn (DiscordChannel $channel) => [
                'key' => $channel->purpose,
                'name' => $channel->name,
                'active' => $channel->is_active,
                'message_id' => $channel->static_message_id,
                'updated_at' => $channel->static_message_updated_at,
            ]);
    }

    public function latestRequests(int $limit = 10): Collection
    {
        if (!Schema::hasTable('discord_sync_requests')) {
            return collect();
        }

        return DiscordSyncRequest::latest('requested_at')->limit($limit)->get();
    }

    public function lastRequest(): ?DiscordSyncRequest
    {
        return $this->latestRequests(1)->first();
    }

    public function maskedApiKey(): string
    {
        $key = (string) config('services.discord_sync.api_key');
        if ($key === '') {
            return 'Niet ingesteld';
        }

        return Str::substr($key, 0, 4).'...'.Str::substr($key, -4);
    }

    public function recordRequest(bool $success, int $itemCount = 0, ?string $error = null, ?string $providedKey = null): void
    {
        if (!Schema::hasTable('discord_sync_requests')) {
            return;
        }

        DiscordSyncRequest::create([
            'api_key_hint' => $providedKey ? Str::substr($providedKey, 0, 4).'...'.Str::substr($providedKey, -4) : null,
            'success' => $success,
            'item_count' => $itemCount,
            'error_message' => $error,
            'requested_at' => now(),
        ]);
    }

    private function panelItems(): Collection
    {
        $active = $this->activePanels()
            ->filter(fn (array $panel) => $panel['active'])
            ->pluck('key')
            ->all();

        if ($active === []) {
            $active = $this->panelKeys();
        }

        return collect($this->panelKeys())
            ->filter(fn (string $key) => in_array($key, $active, true))
            ->map(fn (string $key) => $this->panelItem($key));
    }

    private function panelItem(string $key): array
    {
        $payload = match ($key) {
            'cn-pulse' => [
                'title' => 'CN Pulse',
                'description' => 'Laatste community updates vanuit MijnCN.',
                'fields' => $this->pulseFields(),
                'links' => [['label' => 'Open MijnCN', 'url' => route('dashboard')]],
            ],
            'staff-status' => [
                'title' => 'Staff Status',
                'description' => 'Beschikbaarheid en afwezigheid van het CN-team.',
                'fields' => $this->staffFields(),
                'links' => [['label' => 'Bekijk rooster', 'url' => route('mijncn.module', 'absences')]],
            ],
            'awards-info' => [
                'title' => 'CN Awards 2026',
                'description' => 'Fase, planning en status van de CN Awards.',
                'fields' => $this->awardFields(),
                'links' => [['label' => 'Open Awards', 'url' => route('awards')]],
            ],
            'stem-nu' => [
                'title' => 'Stem nu',
                'description' => 'Actieve stemronde en directe route naar stemmen.',
                'fields' => $this->voteFields(),
                'links' => [['label' => 'Stemmen openen', 'url' => route('awards')]],
            ],
            'trending' => [
                'title' => 'Trending',
                'description' => 'Nominaties die nu opvallen binnen CN.',
                'fields' => $this->topNominationFields(),
                'links' => [['label' => 'Bekijk nominaties', 'url' => route('awards')]],
            ],
            'leaderboard' => [
                'title' => 'Leaderboard',
                'description' => 'Ranglijst op basis van stemmen en reputatie.',
                'fields' => $this->topNominationFields(),
                'links' => [['label' => 'Bekijk leaderboard', 'url' => route('awards')]],
            ],
            'award-logs' => [
                'title' => 'Award Logs',
                'description' => 'Interne status van nominaties, reviews en jury.',
                'fields' => $this->awardLogFields(),
                'links' => [['label' => 'Open awardbeheer', 'url' => route('staff.awards')]],
            ],
        };

        return [
            'type' => 'panel',
            'key' => $key,
            'version' => $this->version($key, $payload),
            'payload' => $payload,
        ];
    }

    private function newsItems(): Collection
    {
        if (!Schema::hasTable('contents')) {
            return collect();
        }

        return Content::published()
            ->where('type', 'news')
            ->where('published_at', '>=', now()->subDay())
            ->latest('published_at')
            ->limit(10)
            ->get()
            ->map(fn (Content $content) => [
                'type' => 'news',
                'id' => 'news-'.$content->id,
                'title' => $content->title,
                'description' => Str::limit($content->excerpt ?: strip_tags($content->body), 240),
                'created_at' => ($content->published_at ?? $content->created_at)->toIso8601String(),
                'links' => [['label' => 'Lees meer', 'url' => route('news.show', $content)]],
            ]);
    }

    private function birthdayItems(): Collection
    {
        if (!Schema::hasTable('users')) {
            return collect();
        }

        return User::whereNotNull('birth_date')
            ->where('birthday_visibility', 'community')
            ->get()
            ->filter(fn (User $user) => $user->birth_date->month === today()->month && $user->birth_date->day === today()->day)
            ->map(fn (User $user) => [
                'type' => 'birthday',
                'id' => 'birthday-'.$user->id.'-'.today()->toDateString(),
                'title' => 'Verjaardag',
                'description' => 'Vandaag is '.$user->name.' jarig. Feliciteer '.($user->name === 'Jesse' ? 'hem' : 'diegene').' in de community.',
                'created_at' => today()->setTime(9, 0)->toIso8601String(),
            ])
            ->values();
    }

    private function absenceItems(): Collection
    {
        if (!Schema::hasTable('absence_requests')) {
            return collect();
        }

        return AbsenceRequest::with('user')
            ->where('status', 'approved')
            ->when(
                Schema::hasColumns('absence_requests', ['starts_at', 'ends_at']),
                fn ($query) => $query->where('ends_at', '>=', now())->where('created_at', '>=', now()->subDay()),
                fn ($query) => $query->whereDate('ends_on', '>=', today())->where('created_at', '>=', now()->subDay())
            )
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (AbsenceRequest $absence) {
                $startsAt = $absence->starts_at ?? $absence->starts_on?->startOfDay();
                $endsAt = $absence->ends_at ?? $absence->ends_on?->endOfDay();
                $reason = trim(preg_replace('/^\[([^\]]+)\]\s*/', '$1 - ', (string) $absence->reason), " \t\n\r\0\x0B-");

                return [
                    'type' => 'staff-absence',
                    'id' => 'absence-'.$absence->id,
                    'title' => 'Staff afwezigheid',
                    'description' => ($absence->user?->name ?: 'Een stafflid').' heeft afwezigheid gemeld.',
                    'created_at' => $absence->created_at->toIso8601String(),
                    'fields' => [[
                        'name' => 'Periode',
                        'value' => $startsAt && $endsAt ? $startsAt->format('d-m-Y H:i').' t/m '.$endsAt->format('d-m-Y H:i') : 'Onbekend',
                        'inline' => true,
                    ], [
                        'name' => 'Reden',
                        'value' => $reason !== '' ? $reason : 'Geen reden opgegeven',
                        'inline' => true,
                    ]],
                    'links' => [['label' => 'Bekijk in MijnCN', 'url' => route('mijncn.module', 'absences')]],
                ];
            });
    }

    private function pulseFields(): array
    {
        return collect(app(CnPulseService::class)->statusCards())
            ->map(fn (array $card) => [
                'name' => $card['label'],
                'value' => $card['value'].' - '.$card['hint'],
                'inline' => true,
            ])
            ->all();
    }

    private function staffFields(): array
    {
        return [[
            'name' => 'Nu afwezig',
            'value' => Schema::hasTable('absence_requests') ? (string) AbsenceRequest::current()->count() : '0',
            'inline' => true,
        ], [
            'name' => 'Staffleden',
            'value' => Schema::hasTable('users') ? (string) User::whereNot('role', 'member')->count() : '0',
            'inline' => true,
        ]];
    }

    private function awardFields(): array
    {
        $edition = $this->currentEdition();
        if (!$edition) {
            return [['name' => 'Status', 'value' => 'Nog geen Awards-editie.', 'inline' => false]];
        }

        return [[
            'name' => 'Fase',
            'value' => $edition->status,
            'inline' => true,
        ], [
            'name' => 'Nominaties',
            'value' => (string) Nomination::whereHas('category', fn ($query) => $query->where('award_edition_id', $edition->id))->count(),
            'inline' => true,
        ], [
            'name' => 'Planning',
            'value' => $this->roundSummary($edition),
            'inline' => false,
        ]];
    }

    private function voteFields(): array
    {
        $edition = $this->currentEdition();
        $round = $edition?->rounds()->where('type', 'public_vote')->where('is_active', true)->latest('starts_at')->first();

        return [[
            'name' => 'Stemronde',
            'value' => $round && $round->isOpen() ? 'Open tot '.$round->ends_at->translatedFormat('d F H:i') : 'Nog niet geopend',
            'inline' => false,
        ], [
            'name' => 'Stemmen',
            'value' => Schema::hasTable('votes') ? (string) Vote::whereNull('superseded_at')->where('is_valid', true)->count() : '0',
            'inline' => true,
        ]];
    }

    private function topNominationFields(): array
    {
        if (!Schema::hasTable('nominations')) {
            return [['name' => 'Leaderboard', 'value' => 'Nog geen nominaties.', 'inline' => false]];
        }

        $items = Nomination::withCount('votes')
            ->whereIn('status', ['approved', 'finalist', 'winner'])
            ->orderByDesc('votes_count')
            ->orderByDesc('reputation_score')
            ->limit(5)
            ->get();

        return $items->isEmpty()
            ? [['name' => 'Leaderboard', 'value' => 'Nog geen goedgekeurde nominaties.', 'inline' => false]]
            : $items->values()->map(fn (Nomination $nomination, int $index) => [
                'name' => '#'.($index + 1).' '.$nomination->nominee_name,
                'value' => $nomination->votes_count.' stemmen - reputatie '.number_format((float) $nomination->reputation_score, 1, ',', '.'),
                'inline' => false,
            ])->all();
    }

    private function awardLogFields(): array
    {
        $edition = $this->currentEdition();
        if (!$edition) {
            return [['name' => 'Logboek', 'value' => 'Nog geen actieve editie.', 'inline' => false]];
        }

        $query = Nomination::whereHas('category', fn ($category) => $category->where('award_edition_id', $edition->id));

        return [[
            'name' => 'In behandeling',
            'value' => (string) (clone $query)->where('status', 'pending')->count(),
            'inline' => true,
        ], [
            'name' => 'Goedgekeurd',
            'value' => (string) (clone $query)->whereIn('status', ['approved', 'finalist', 'winner'])->count(),
            'inline' => true,
        ], [
            'name' => 'Afgekeurd / dubbel',
            'value' => (string) (clone $query)->whereIn('status', ['rejected', 'duplicate'])->count(),
            'inline' => true,
        ]];
    }

    private function currentEdition(): ?AwardEdition
    {
        if (!Schema::hasTable('award_editions')) {
            return null;
        }

        return AwardEdition::where('type', 'cn_awards')->latest('year')->first();
    }

    private function roundSummary(AwardEdition $edition): string
    {
        $round = $edition->rounds()
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->first();

        if (!$round) {
            $next = $edition->rounds()->where('starts_at', '>', now())->orderBy('starts_at')->first();

            return $next ? 'Volgende fase: '.$next->type.' op '.$next->starts_at->translatedFormat('d F H:i') : 'Geen actieve planning.';
        }

        return $round->type.' loopt tot '.$round->ends_at->translatedFormat('d F H:i');
    }

    private function version(string $key, array $payload): string
    {
        return 'v'.substr(sha1($key.json_encode($payload)), 0, 12);
    }
}
