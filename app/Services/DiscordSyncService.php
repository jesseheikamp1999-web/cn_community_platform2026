<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use App\Models\AwardEdition;
use App\Models\Content;
use App\Models\DiscordChannel;
use App\Models\DiscordSyncPanel;
use App\Models\DiscordSyncRequest;
use App\Models\DiscordSyncSetting;
use App\Models\Nomination;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DiscordSyncService
{
    public function response(string $channel = 'all', ?string $knownVersion = null): array
    {
        $generatedAt = now()->toIso8601String();

        if ($channel === '' || $channel === 'all') {
            return [
                'success' => true,
                'generated_at' => $generatedAt,
                'refresh_after_seconds' => $this->defaultRefreshAfterSeconds(),
                'items' => $this->items(),
            ];
        }

        if (!in_array($channel, $this->panelKeys(), true)) {
            return [
                'success' => false,
                'message' => 'Onbekend sync-kanaal.',
            ];
        }

        $item = $this->panelItem($channel, $knownVersion);

        return [
            'success' => true,
            'generated_at' => $generatedAt,
            'changed' => $item['changed'],
            'refresh_after_seconds' => $item['refresh_after_seconds'],
            'item' => $item,
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
        return $this->syncPanels()->map(function (array $panel): array {
            $preview = $this->panelItem($panel['key']);

            return [
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
                'current_version' => $preview['version'],
                'preview' => $preview['payload'],
            ];
        });
    }

    public function previewPanels(): Collection
    {
        return collect($this->panelKeys())->mapWithKeys(fn (string $key) => [$key => $this->panelItem($key)]);
    }

    public function latestLooseIds(): array
    {
        return [
            'news' => collect($this->newsItems())->pluck('id')->take(5)->values()->all(),
            'birthdays' => collect($this->birthdayItems())->pluck('id')->take(5)->values()->all(),
            'staff_absences' => collect($this->absenceItems())->pluck('id')->take(5)->values()->all(),
        ];
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
        $key = $this->configuredApiKey();
        if ($key === '') {
            return 'Niet ingesteld';
        }

        return Str::substr($key, 0, 4).'...'.Str::substr($key, -4);
    }

    public function configuredApiKey(): string
    {
        if (Schema::hasTable('discord_sync_settings')) {
            $customKey = trim((string) DiscordSyncSetting::query()->where('key', 'api_key')->value('value'));
            if ($customKey !== '') {
                return $customKey;
            }
        }

        return trim((string) config('services.discord_sync.api_key'));
    }

    public function apiKeySource(): string
    {
        if (Schema::hasTable('discord_sync_settings')) {
            $customKey = trim((string) DiscordSyncSetting::query()->where('key', 'api_key')->value('value'));
            if ($customKey !== '') {
                return 'MijnCN';
            }
        }

        return config('services.discord_sync.api_key') ? '.env' : 'Niet ingesteld';
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
            'ready_items_count' => count($this->items()),
            'latest_loose_ids' => $this->latestLooseIds(),
            'api_status' => $this->configuredApiKey() !== '' ? 'ingesteld' : 'niet ingesteld',
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
        $version = $this->version($key, $payload, $panel);

        return [
            'type' => 'panel',
            'key' => $key,
            'channel' => $key,
            'version' => $version,
            'changed' => $knownVersion === null || $knownVersion === '' ? true : !hash_equals($version, $knownVersion),
            'refresh_after_seconds' => $panel['refresh_after_seconds'],
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
            'trending' => $this->trendingFields(),
            'leaderboard' => $this->leaderboardFields(),
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
            'description' => $this->plainText($panel['description'], 220),
            'fields' => array_slice($fields, 0, 6),
            'links' => $links,
        ];
    }

    private function defaultPanelDefinitions(): array
    {
        return [
            'cn-pulse' => [
                'label' => 'CN Pulse',
                'title' => 'CN Pulse',
                'description' => 'Dagelijkse community-feed met nominaties, staff-updates en highlights uit MijnCN.',
                'button_label' => 'Open MijnCN',
                'button_url' => route('dashboard'),
                'secondary_button_label' => 'Open Pulse',
                'secondary_button_url' => route('mijncn.module', 'pulse'),
                'refresh_after_seconds' => 300,
            ],
            'staff-status' => [
                'label' => 'Staff Status',
                'title' => 'Staff Status',
                'description' => 'Rooster, bezetting en afwezigheden van het CN-team in een compact overzicht.',
                'button_label' => 'Open rooster',
                'button_url' => route('mijncn.module', 'absences'),
                'secondary_button_label' => 'Staffpagina',
                'secondary_button_url' => route('staff'),
                'refresh_after_seconds' => 300,
            ],
            'awards-info' => [
                'label' => 'Awards Info',
                'title' => 'CN Awards 2026',
                'description' => 'Spotlightcategorieën, planning en uitleg voor nominaties, stemmen en finale.',
                'button_label' => 'Open Awards',
                'button_url' => route('awards'),
                'secondary_button_label' => 'Awards in MijnCN',
                'secondary_button_url' => route('mijncn.module', 'nominations'),
                'refresh_after_seconds' => 300,
            ],
            'stem-nu' => [
                'label' => 'Stem Nu',
                'title' => 'Stem nu',
                'description' => 'Actieve stemrondes, deadlines en directe route naar jouw stem.',
                'button_label' => 'Open stemronde',
                'button_url' => route('awards'),
                'secondary_button_label' => 'Bekijk categorieën',
                'secondary_button_url' => route('awards'),
                'refresh_after_seconds' => 300,
            ],
            'trending' => [
                'label' => 'Trending',
                'title' => 'Trending',
                'description' => 'Wat leeft er vandaag in CN: nominaties, communitymomenten en actieve onderwerpen.',
                'button_label' => 'Bekijk trend',
                'button_url' => route('awards'),
                'secondary_button_label' => 'Open community',
                'secondary_button_url' => route('mijncn.module', 'community'),
                'refresh_after_seconds' => 300,
            ],
            'leaderboard' => [
                'label' => 'Leaderboard',
                'title' => 'Leaderboard',
                'description' => 'Top 5, stijgers en reputatiescore rondom de CN Awards.',
                'button_label' => 'Volledig leaderboard',
                'button_url' => route('awards'),
                'secondary_button_label' => 'Open Awards',
                'secondary_button_url' => route('awards'),
                'refresh_after_seconds' => 300,
            ],
            'award-logs' => [
                'label' => 'Award Logs',
                'title' => 'Award Logs',
                'description' => 'Korte beheerlogs over nominaties, stemrondes en bevestigde awardacties.',
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
                'channel' => 'news',
                'created_at' => ($content->published_at ?? $content->created_at)->toIso8601String(),
                'payload' => [
                    'title' => $this->plainText($content->title, 120),
                    'description' => $this->plainText($content->excerpt ?: strip_tags($content->body), 220),
                    'fields' => filled(data_get($content->meta, 'source'))
                        ? [[
                            'name' => 'Bron',
                            'value' => $this->plainText((string) data_get($content->meta, 'source'), 80),
                            'inline' => true,
                        ]]
                        : [],
                    'links' => [[
                        'label' => 'Bekijk update',
                        'url' => route('news.show', $content),
                    ]],
                ],
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
                'channel' => 'birthdays',
                'created_at' => today()->setTime(9, 0)->toIso8601String(),
                'payload' => [
                    'name' => $user->name,
                    'description' => 'Vandaag zetten we '.$user->name.' extra in het zonnetje.',
                ],
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
                fn ($query) => $query
                    ->where(function ($absenceQuery) {
                        $absenceQuery->where('ends_at', '>=', now())
                            ->orWhere(function ($legacyQuery) {
                                $legacyQuery->whereNull('ends_at')
                                    ->whereDate('ends_on', '>=', today());
                            });
                    })
                    ->where('created_at', '>=', now()->subDay()),
                fn ($query) => $query->whereDate('ends_on', '>=', today())->where('created_at', '>=', now()->subDay())
            )
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (AbsenceRequest $absence) {
                $startsAt = $absence->starts_at ?? $absence->starts_on?->startOfDay();
                $endsAt = $absence->ends_at ?? $absence->ends_on?->endOfDay();
                $reason = trim(preg_replace('/^\[[^\]]+\]\s*/', '', (string) $absence->reason));

                return [
                    'type' => 'staff-absence',
                    'id' => 'absence-'.$absence->id,
                    'channel' => 'staff-status',
                    'created_at' => $absence->created_at->toIso8601String(),
                    'payload' => [
                        'name' => $absence->user?->name ?: 'Een stafflid',
                        'reason' => $reason !== '' ? $this->plainText($reason, 160) : 'Afwezig voor stafftaken',
                        'period' => $startsAt && $endsAt
                            ? $startsAt->translatedFormat('d F Y H:i').' - '.$endsAt->translatedFormat('d F Y H:i')
                            : 'Onbekende periode',
                        'roster_url' => route('mijncn.module', 'absences'),
                    ],
                ];
            });
    }

    private function pulseFields(): array
    {
        $statusCards = collect(app(CnPulseService::class)->statusCards());
        $highlight = $this->latestNewsTitle() ?: 'Vandaag bouwen we verder aan Awards, MijnCN en de community.';
        $question = $this->dailyCommunityQuestion();
        $topMoment = $statusCards->first()['value'] ?? 'Geen highlight beschikbaar';
        $miniGoal = $this->currentEdition()?->status === 'nominations'
            ? 'Nomineer vandaag iemand die CN zichtbaar beter maakt.'
            : 'Houd MijnCN in de gaten voor nieuws, badges en nieuwe updates.';

        return [
            $this->field('Daghighlight', $highlight),
            $this->field('Actiepunt', $this->pulseActionPoint()),
            $this->field('Communityvraag', $question),
            $this->field('Topmoment', $topMoment.' · '.$this->pulseTopMomentMeta()),
            $this->field('Mini-doel', $miniGoal),
        ];
    }

    private function staffFields(): array
    {
        $staffMembers = Schema::hasTable('users') ? User::whereNot('role', 'member')->count() : 0;
        $absentNow = Schema::hasTable('absence_requests') ? AbsenceRequest::current()->count() : 0;
        $available = max(0, $staffMembers - $absentNow);

        return [
            $this->field('Rooster', $this->nextAbsenceSummary()),
            $this->field('Bezetting', $available.' beschikbaar van '.$staffMembers.' staffleden'),
            $this->field('Afwezigheden', $absentNow > 0 ? $absentNow.' staffleden staan nu op afwezig.' : 'Iedereen staat nu beschikbaar.'),
            $this->field('Open gaten', $absentNow > 1 ? 'Extra aandacht nodig voor support en events.' : 'Geen acute gaten in de bezetting.'),
            $this->field('Staff focus', $this->staffFocusText()),
        ];
    }

    private function awardFields(): array
    {
        $edition = $this->currentEdition();
        if (!$edition) {
            return [$this->field('Awards status', 'Nog geen actieve Awards-editie gekoppeld aan de sync.')];
        }

        $nominationRound = $edition->rounds()->where('type', 'nomination')->orderBy('starts_at')->first();
        $voteRound = $edition->rounds()->where('type', 'public_vote')->orderBy('starts_at')->first();
        $spotlights = $edition->categories()->orderBy('sort_order')->limit(3)->pluck('name')->implode(', ');

        return [
            $this->field('Spotlightcategorieën', $spotlights !== '' ? $spotlights : 'Wordt nog samengesteld.'),
            $this->field('Award uitleg', 'De CN Awards combineren communitystemmen, juryrapporten en een publieke finale.'),
            $this->field('Nominatieperiode', $this->roundWindowText($nominationRound?->starts_at, $nominationRound?->ends_at)),
            $this->field('Stemperiode', $this->roundWindowText($voteRound?->starts_at, $voteRound?->ends_at)),
            $this->field('Prijzen & beloningen', 'Winnaars krijgen zichtbaarheid, reputatiegroei en een plek in de Hall of Fame.'),
        ];
    }

    private function voteFields(): array
    {
        $edition = $this->currentEdition();
        $round = $edition?->rounds()->where('type', 'public_vote')->where('is_active', true)->latest('starts_at')->first();
        $votes = Schema::hasTable('votes') ? Vote::whereNull('superseded_at')->where('is_valid', true)->count() : 0;

        return [
            $this->field('Actieve stemrondes', $round && $round->isOpen() ? 'De stemronde staat nu live voor '.$edition?->name.'.' : 'Er staat op dit moment geen openbare stemronde open.'),
            $this->field('Deadline', $round && $round->isOpen() ? 'Stemmen kan tot '.$round->ends_at->translatedFormat('d F Y H:i') : 'De volgende deadline verschijnt zodra de ronde opent.'),
            $this->field('Waarom stemmen?', 'Met jouw stem help je bepalen welke community, maker of server het podium verdient.'),
            $this->field('Direct stemmen', $votes.' geldige stemmen geregistreerd · open de stemknop hieronder om mee te doen.'),
        ];
    }

    private function trendingFields(): array
    {
        $topNomination = $this->rankedNominations()->first();

        return [
            $this->field('Topkanalen', 'Awards, community-updates en staff-overzichten trekken nu de meeste aandacht.'),
            $this->field('Populaire games', 'Minecraft-servers en communityprojecten voeren nog steeds de boventoon in nominaties.'),
            $this->field('Actieve ideeën', $this->latestNewsTitle() ?: 'Nieuwe ideeën verschijnen vooral rondom Awards, partners en stafftools.'),
            $this->field('Actieve polls', $topNomination ? $topNomination->nominee_name.' krijgt momenteel de meeste aandacht in de ranglijsten.' : 'Nog geen actieve poll-highlight beschikbaar.'),
            $this->field('Communitymoment van de dag', $this->pulseTopMomentMeta()),
        ];
    }

    private function leaderboardFields(): array
    {
        $ranked = $this->rankedNominations();
        $topFive = $ranked->take(5);
        $winner = $topFive->first();
        $riser = $topFive->get(1);
        $almost = $topFive->get(3);

        return [
            $this->field('Top 5', $topFive->isNotEmpty() ? $topFive->values()->map(fn (Nomination $nomination, int $index) => '#'.($index + 1).' '.$nomination->nominee_name)->implode(' · ') : 'Nog geen top 5 beschikbaar.'),
            $this->field('Dagwinnaar', $winner ? $winner->nominee_name.' leidt met '.$winner->votes_count.' stemmen.' : 'Nog geen dagwinnaar beschikbaar.'),
            $this->field('Stijger van de dag', $riser ? $riser->nominee_name.' klimt mee omhoog in de ranking.' : 'Er is nog geen stijger bepaald.'),
            $this->field('Bijna top 3', $almost ? $almost->nominee_name.' zit dicht tegen het podium aan.' : 'De top 3 krijgt vorm zodra er meer stemmen zijn.'),
            $this->field('Volledig leaderboard', 'Open de Awards-pagina voor alle categorieën, reputatiescore en stemstatus.'),
        ];
    }

    private function awardLogFields(): array
    {
        $edition = $this->currentEdition();
        if (!$edition) {
            return [$this->field('Award logs', 'Nog geen actieve editie, dus er zijn ook nog geen beheerlogs.')];
        }

        $query = Nomination::whereHas('category', fn ($category) => $category->where('award_edition_id', $edition->id));
        $latestNomination = (clone $query)->latest('updated_at')->first();

        return [
            $this->field('Nominatie geplaatst', (string) (clone $query)->where('status', 'pending')->count().' kandidaten wachten nog op controle.'),
            $this->field('Stemronde geopend', $edition->status === 'voting' ? 'Publieksstemmen staan momenteel open.' : 'De stemronde is nu niet actief.'),
            $this->field('Winnaar bevestigd', (string) (clone $query)->where('status', 'winner')->count().' winnaars staan al bevestigd.'),
            $this->field('Beheeractie', $latestNomination ? $latestNomination->nominee_name.' is laatst bijgewerkt op '.$latestNomination->updated_at->translatedFormat('d F H:i').'.' : 'Nog geen recente beheeractie beschikbaar.'),
        ];
    }

    private function currentEdition(): ?AwardEdition
    {
        if (!Schema::hasTable('award_editions')) {
            return null;
        }

        return AwardEdition::where('type', 'cn_awards')->latest('year')->first();
    }

    private function rankedNominations(): Collection
    {
        if (!Schema::hasTable('nominations')) {
            return collect();
        }

        return Nomination::withCount('votes')
            ->whereIn('status', ['approved', 'finalist', 'winner'])
            ->orderByDesc('votes_count')
            ->orderByDesc('reputation_score')
            ->limit(10)
            ->get();
    }

    private function roundWindowText($startsAt, $endsAt): string
    {
        if (!$startsAt || !$endsAt) {
            return 'Nog niet ingepland';
        }

        return $startsAt->translatedFormat('d F H:i').' - '.$endsAt->translatedFormat('d F H:i');
    }

    private function nextAbsenceSummary(): string
    {
        if (!Schema::hasTable('absence_requests')) {
            return 'Roostergegevens zijn nog niet beschikbaar.';
        }

        $next = AbsenceRequest::with('user')
            ->where('status', 'approved')
            ->when(
                Schema::hasColumns('absence_requests', ['starts_at', 'ends_at']),
                fn ($query) => $query->where('ends_at', '>=', now())->orderBy('starts_at'),
                fn ($query) => $query->whereDate('ends_on', '>=', today())->orderBy('starts_on')
            )
            ->first();

        if (!$next) {
            return 'Er staan nu geen open afwezigheden gepland.';
        }

        $start = $next->starts_at ?? $next->starts_on?->startOfDay();
        return ($next->user?->name ?: 'Stafflid').' vanaf '.($start?->translatedFormat('d F H:i') ?? 'onbekend');
    }

    private function pulseActionPoint(): string
    {
        $edition = $this->currentEdition();

        return match ($edition?->status) {
            'nominations' => 'Nomineer vandaag iemand die CN sterker maakt.',
            'voting' => 'Breng je stem uit en deel de stemronde met de community.',
            'jury' => 'Juryleden kunnen nu hun rapporten afronden in MijnCN.',
            'finale' => 'De finale komt eraan, houd de reveal-momenten in de gaten.',
            default => 'Check MijnCN voor nieuwe staffupdates, nieuws en partnerkansen.',
        };
    }

    private function pulseTopMomentMeta(): string
    {
        $news = $this->newsItems()->count();
        $birthdays = $this->birthdayItems()->count();
        $absences = $this->absenceItems()->count();

        return $news.' nieuws · '.$birthdays.' verjaardagen · '.$absences.' staffmeldingen';
    }

    private function staffFocusText(): string
    {
        $edition = $this->currentEdition();
        if ($edition?->status === 'nominations') {
            return 'Controleer nieuwe nominaties en help de community met duidelijke feedback.';
        }

        if ($edition?->status === 'voting') {
            return 'Houd stemvragen, support en deadlines vandaag extra scherp in de gaten.';
        }

        return 'Focus vandaag op support, teamafstemming en zichtbaarheid binnen de community.';
    }

    private function latestNewsTitle(): ?string
    {
        if (!Schema::hasTable('contents')) {
            return null;
        }

        $news = Content::published()->where('type', 'news')->latest('published_at')->first();

        return $news ? $this->plainText($news->title, 120) : null;
    }

    private function dailyCommunityQuestion(): string
    {
        $questions = [
            'Wie verdient vandaag volgens jou extra spotlight binnen CN?',
            'Welk project of teammoment wil jij deze week terugzien in de spotlight?',
            'Welke categorie zou volgens jou extra aandacht moeten krijgen bij de Awards?',
            'Wat zou vandaag de community meteen een stap beter maken?',
            'Wie heeft de community deze week echt sterker gemaakt?',
        ];

        return $questions[(int) now()->dayOfYear % count($questions)];
    }

    private function plainText(string $value, int $limit = 240): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($value))), $limit);
    }

    private function field(string $name, string $value, bool $inline = false): array
    {
        return [
            'name' => Str::limit($name, 80),
            'value' => Str::limit($value, 320),
            'inline' => $inline,
        ];
    }

    private function version(string $key, array $payload, array $panel): string
    {
        $stamp = $this->versionStampForPanel($key, $panel);
        $fingerprint = substr(sha1(json_encode($payload)), 0, 8);

        return $key.'-'.$stamp->format('Y-m-d-Hi').'-'.$fingerprint;
    }

    private function versionStampForPanel(string $key, array $panel): Carbon
    {
        $candidates = collect([
            $panel['updated_at'] ?? null,
            $this->panelContentTimestamp($key),
        ])->filter();

        /** @var Carbon|null $latest */
        $latest = $candidates->map(fn ($value) => $value instanceof Carbon ? $value : Carbon::parse($value))
            ->sortByDesc(fn (Carbon $value) => $value->timestamp)
            ->first();

        return ($latest ?: now())->copy()->seconds(0);
    }

    private function panelContentTimestamp(string $key): ?Carbon
    {
        return match ($key) {
            'cn-pulse' => $this->latestTimestamp([
                Schema::hasTable('contents') ? Content::query()->max('updated_at') : null,
                Schema::hasTable('nominations') ? Nomination::query()->max('updated_at') : null,
                Schema::hasTable('absence_requests') ? AbsenceRequest::query()->max('updated_at') : null,
                Schema::hasTable('users') ? User::query()->max('updated_at') : null,
            ]),
            'staff-status' => $this->latestTimestamp([
                Schema::hasTable('absence_requests') ? AbsenceRequest::query()->max('updated_at') : null,
                Schema::hasTable('users') ? User::query()->max('updated_at') : null,
            ]),
            'awards-info', 'stem-nu', 'trending', 'leaderboard', 'award-logs' => $this->latestTimestamp([
                Schema::hasTable('award_editions') ? AwardEdition::query()->max('updated_at') : null,
                Schema::hasTable('nominations') ? Nomination::query()->max('updated_at') : null,
                Schema::hasTable('votes') ? Vote::query()->max('updated_at') : null,
            ]),
            default => null,
        };
    }

    private function latestTimestamp(array $values): ?Carbon
    {
        return collect($values)
            ->filter()
            ->map(fn ($value) => $value instanceof Carbon ? $value : Carbon::parse($value))
            ->sortByDesc(fn (Carbon $value) => $value->timestamp)
            ->first();
    }
}
