<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use App\Models\AwardEdition;
use App\Models\Content;
use App\Models\DiscordChannel;
use App\Models\DiscordSyncPanel;
use App\Models\DiscordSyncRequest;
use App\Models\Nomination;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DiscordSyncService
{
    public function response(string $channel = 'all', ?string $knownVersion = null): array
    {
        if ($channel === '' || $channel === 'all') {
            $items = $this->items();

            return [
                'success' => true,
                'generated_at' => now()->toIso8601String(),
                'count' => count($items),
                'refresh_after_seconds' => $this->defaultRefreshAfterSeconds(),
                'items' => $items,
            ];
        }

        if (!in_array($channel, $this->panelKeys(), true)) {
            return [
                'success' => false,
                'message' => 'Onbekend sync-kanaal.',
                'available_channels' => $this->panelKeys(),
            ];
        }

        return [
            'success' => true,
            'generated_at' => now()->toIso8601String(),
            'item' => $this->panelItem($channel, $knownVersion),
        ];
    }

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
        return $this->syncPanels()->map(fn (array $panel) => [
            'key' => $panel['key'],
            'name' => $panel['channel_name'],
            'active' => $panel['channel_active'] && $panel['is_active'],
            'message_id' => $panel['message_id'],
            'updated_at' => $panel['updated_at'],
            'channel_id' => $panel['channel_id'],
            'refresh_after_seconds' => $panel['refresh_after_seconds'],
            'title' => $panel['title'],
            'description' => $panel['description'],
            'button_label' => $panel['button_label'],
            'button_url' => $panel['button_url'],
            'secondary_button_label' => $panel['secondary_button_label'],
            'secondary_button_url' => $panel['secondary_button_url'],
        ]);
    }

    public function syncPanels(): Collection
    {
        $settings = Schema::hasTable('discord_sync_panels')
            ? DiscordSyncPanel::query()->get()->keyBy('key')
            : collect();
        $channels = Schema::hasTable('discord_channels')
            ? DiscordChannel::whereIn('purpose', $this->panelKeys())->get()->keyBy('purpose')
            : collect();

        return collect($this->defaultPanelDefinitions())->map(function (array $defaults, string $key) use ($settings, $channels) {
            /** @var DiscordSyncPanel|null $setting */
            $setting = $settings->get($key);
            /** @var DiscordChannel|null $channel */
            $channel = $channels->get($key);

            return [
                'key' => $key,
                'label' => $defaults['label'],
                'title' => $setting?->title ?: $defaults['title'],
                'description' => $setting?->description ?: $defaults['description'],
                'button_label' => $setting?->button_label ?: $defaults['button_label'],
                'button_url' => $setting?->button_url ?: $defaults['button_url'],
                'secondary_button_label' => $setting?->secondary_button_label ?: $defaults['secondary_button_label'],
                'secondary_button_url' => $setting?->secondary_button_url ?: $defaults['secondary_button_url'],
                'refresh_after_seconds' => max(30, min(3600, (int) ($setting?->refresh_after_seconds ?: $defaults['refresh_after_seconds']))),
                'is_active' => $setting?->is_active ?? true,
                'channel_name' => $channel?->name ?: $defaults['label'],
                'channel_id' => $channel?->discord_channel_id,
                'channel_active' => $channel?->is_active ?? true,
                'message_id' => $channel?->static_message_id,
                'updated_at' => $channel?->static_message_updated_at,
            ];
        })->values();
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

    public function diagnostics(): array
    {
        return [
            'endpoint' => url('/api/discord-sync'),
            'all_url' => url('/api/discord-sync?channel=all'),
            'single_channel_example' => url('/api/discord-sync?channel=awards-info'),
            'panels' => count($this->panelKeys()),
            'active_panels' => $this->activePanels()->where('active', true)->count(),
            'default_refresh_after_seconds' => $this->defaultRefreshAfterSeconds(),
        ];
    }

    public function recordRequest(
        bool $success,
        int $itemCount = 0,
        ?string $error = null,
        ?string $providedKey = null,
        ?string $channelKey = null,
        int $statusCode = 200,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        if (!Schema::hasTable('discord_sync_requests')) {
            return;
        }

        DiscordSyncRequest::create([
            'api_key_hint' => $providedKey ? Str::substr($providedKey, 0, 4).'...'.Str::substr($providedKey, -4) : null,
            'channel_key' => $channelKey,
            'success' => $success,
            'status_code' => $statusCode,
            'item_count' => $itemCount,
            'error_message' => $error,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? Str::limit($userAgent, 255, '') : null,
            'requested_at' => now(),
        ]);
    }

    private function panelItems(): Collection
    {
        return $this->syncPanels()
            ->filter(fn (array $panel) => $panel['is_active'] && $panel['channel_active'])
            ->map(fn (array $panel) => $this->panelItem($panel['key']));
    }

    private function panelItem(string $key, ?string $knownVersion = null): array
    {
        $panel = $this->syncPanels()->firstWhere('key', $key);
        $payload = $this->buildPayload($panel);
        $version = $this->version($key, [
            'title' => $panel['title'],
            'description' => $panel['description'],
            'button_label' => $panel['button_label'],
            'button_url' => $panel['button_url'],
            'secondary_button_label' => $panel['secondary_button_label'],
            'secondary_button_url' => $panel['secondary_button_url'],
            'stats' => $this->statsForPanel($key),
            'payload' => $payload,
        ]);

        return [
            'type' => 'panel',
            'key' => $key,
            'channel' => $key,
            'label' => $panel['label'],
            'channel_id' => $panel['channel_id'],
            'channel_name' => $panel['channel_name'],
            'refresh_after_seconds' => $panel['refresh_after_seconds'],
            'version' => $version,
            'changed' => $knownVersion === null || $knownVersion === '' ? true : !hash_equals($version, $knownVersion),
            'stats' => $this->statsForPanel($key),
            'payload' => $payload,
        ];
    }

    private function buildPayload(array $panel): array
    {
        $fields = match ($panel['key']) {
            'cn-pulse' => $this->pulseFields(),
            'staff-status' => $this->staffFields(),
            'awards-info' => $this->awardFields(),
            'stem-nu' => $this->voteFields(),
            'trending', 'leaderboard' => $this->topNominationFields(),
            'award-logs' => $this->awardLogFields(),
        };

        $links = collect([
            [
                'label' => $panel['button_label'],
                'url' => $panel['button_url'],
            ],
            $panel['secondary_button_label'] && $panel['secondary_button_url'] ? [
                'label' => $panel['secondary_button_label'],
                'url' => $panel['secondary_button_url'],
            ] : null,
        ])->filter(fn (?array $link) => $link && filled($link['label']) && filled($link['url']))->values()->all();

        return [
            'title' => $panel['title'],
            'description' => $panel['description'],
            'fields' => $fields,
            'links' => $links,
        ];
    }

    private function defaultPanelDefinitions(): array
    {
        return [
            'cn-pulse' => [
                'label' => 'CN Pulse',
                'title' => 'CN Pulse',
                'description' => 'Laatste community updates vanuit MijnCN.',
                'button_label' => 'Open MijnCN',
                'button_url' => route('dashboard'),
                'secondary_button_label' => 'Bekijk Pulse',
                'secondary_button_url' => route('mijncn.module', 'pulse'),
                'refresh_after_seconds' => 300,
            ],
            'staff-status' => [
                'label' => 'Staff Status',
                'title' => 'Staff Status',
                'description' => 'Beschikbaarheid en afwezigheid van het CN-team.',
                'button_label' => 'Open rooster',
                'button_url' => route('mijncn.module', 'absences'),
                'secondary_button_label' => 'Staffpagina',
                'secondary_button_url' => route('staff'),
                'refresh_after_seconds' => 300,
            ],
            'awards-info' => [
                'label' => 'Awards Info',
                'title' => 'CN Awards 2026',
                'description' => 'Fase, planning en status van de CN Awards.',
                'button_label' => 'Open Awards',
                'button_url' => route('awards'),
                'secondary_button_label' => 'MijnCN Awards',
                'secondary_button_url' => route('mijncn.module', 'nominations'),
                'refresh_after_seconds' => 300,
            ],
            'stem-nu' => [
                'label' => 'Stem Nu',
                'title' => 'Stem nu',
                'description' => 'Actieve stemronde en directe route naar stemmen.',
                'button_label' => 'Stemmen openen',
                'button_url' => route('awards'),
                'secondary_button_label' => 'Leaderboard',
                'secondary_button_url' => route('awards'),
                'refresh_after_seconds' => 300,
            ],
            'trending' => [
                'label' => 'Trending',
                'title' => 'Trending',
                'description' => 'Nominaties die nu opvallen binnen CN.',
                'button_label' => 'Bekijk nominaties',
                'button_url' => route('awards'),
                'secondary_button_label' => 'Open MijnCN',
                'secondary_button_url' => route('dashboard'),
                'refresh_after_seconds' => 300,
            ],
            'leaderboard' => [
                'label' => 'Leaderboard',
                'title' => 'Leaderboard',
                'description' => 'Ranglijst op basis van stemmen en reputatie.',
                'button_label' => 'Bekijk leaderboard',
                'button_url' => route('awards'),
                'secondary_button_label' => 'Open Awards',
                'secondary_button_url' => route('awards'),
                'refresh_after_seconds' => 300,
            ],
            'award-logs' => [
                'label' => 'Award Logs',
                'title' => 'Award Logs',
                'description' => 'Interne status van nominaties, reviews en jury.',
                'button_label' => 'Open awardbeheer',
                'button_url' => route('staff.awards'),
                'secondary_button_label' => 'Open MijnCN',
                'secondary_button_url' => route('dashboard'),
                'refresh_after_seconds' => 300,
            ],
        ];
    }

    private function defaultRefreshAfterSeconds(): int
    {
        return (int) $this->syncPanels()->max('refresh_after_seconds') ?: 300;
    }

    private function statsForPanel(string $key): array
    {
        return match ($key) {
            'cn-pulse' => [
                'cards' => count($this->pulseFields()),
                'news' => $this->newsItems()->count(),
                'birthdays' => $this->birthdayItems()->count(),
                'absences' => $this->absenceItems()->count(),
            ],
            'staff-status' => [
                'absent_now' => Schema::hasTable('absence_requests') ? AbsenceRequest::current()->count() : 0,
                'staff_total' => Schema::hasTable('users') ? User::whereNot('role', 'member')->count() : 0,
            ],
            'awards-info' => [
                'edition' => $this->currentEdition()?->name,
                'status' => $this->currentEdition()?->status,
            ],
            'stem-nu' => [
                'votes' => Schema::hasTable('votes') ? Vote::whereNull('superseded_at')->where('is_valid', true)->count() : 0,
            ],
            'trending', 'leaderboard' => [
                'tracked_nominations' => Schema::hasTable('nominations')
                    ? Nomination::whereIn('status', ['approved', 'finalist', 'winner'])->count()
                    : 0,
            ],
            'award-logs' => [
                'pending_reviews' => Schema::hasTable('nominations') ? Nomination::where('status', 'pending')->count() : 0,
            ],
        };
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
