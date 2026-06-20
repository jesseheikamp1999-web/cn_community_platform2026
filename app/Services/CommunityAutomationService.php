<?php

namespace App\Services;

use App\Models\AutomationLog;
use App\Models\AwardEdition;
use App\Models\AwardRound;
use App\Models\DiscordChannel;
use App\Models\DiscordDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class CommunityAutomationService
{
    public function __construct(private readonly DiscordService $discord)
    {
    }

    public function run(): array
    {
        return [
            'award_phases' => $this->processAwardPhases(),
            'birthdays' => $this->processBirthdays(),
        ];
    }

    public function processAwardPhases(): int
    {
        $processed = 0;

        AwardRound::with('edition')
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->whereHas('edition', fn ($query) => $query->whereNotIn('status', ['published', 'archived']))
            ->orderBy('starts_at')
            ->get()
            ->each(function (AwardRound $round) use (&$processed): void {
                $status = match ($round->type) {
                    'nomination' => 'nominations',
                    'public_vote' => 'voting',
                    'jury' => 'jury',
                    'finale' => 'finale',
                };

                if ($round->edition->status !== $status) {
                    $round->edition->update(['status' => $status]);
                }

                $key = 'awards:round-opened:'.$round->id;
                if ($this->claim($key, 'awards_round', [
                    'edition_id' => $round->award_edition_id,
                    'round_id' => $round->id,
                    'round_type' => $round->type,
                ])) {
                    $this->announceAwardRound($round);
                    $processed++;
                }
            });

        AwardEdition::where('type', 'mini_awards')
            ->where('status', 'voting')
            ->whereHas('rounds', fn ($query) => $query
                ->where('type', 'public_vote')
                ->where('is_active', true)
                ->where('ends_at', '<', now()))
            ->each(function (AwardEdition $edition): void {
                $edition->update(['status' => 'finale']);
            });

        return $processed;
    }

    public function processBirthdays(): int
    {
        $birthdays = User::whereNotNull('birth_date')
            ->whereNot('birthday_visibility', 'private')
            ->get()
            ->filter(fn (User $user) => $user->birth_date->month === today()->month
                && $user->birth_date->day === today()->day);

        $processed = 0;
        foreach ($birthdays as $birthdayUser) {
            $key = 'birthday:'.$birthdayUser->id.':'.today()->format('Y');
            if (!$this->claim($key, 'birthday', ['user_id' => $birthdayUser->id])) {
                continue;
            }

            $age = today()->year - $birthdayUser->birth_date->year;
            $recipients = User::where('birthday_notifications', true)
                ->where('id', '!=', $birthdayUser->id)
                ->when(
                    $birthdayUser->birthday_visibility === 'staff',
                    fn ($query) => $query->whereNot('role', 'member')
                )
                ->get();

            if ($recipients->isNotEmpty()) {
                DB::table('notifications')->insert($recipients->map(fn (User $recipient) => [
                    'id' => (string) Str::uuid(),
                    'type' => 'community.birthday',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $recipient->id,
                    'data' => json_encode([
                        'title' => $birthdayUser->name.' is jarig',
                        'message' => 'Vandaag viert '.$birthdayUser->name.' de '.$age.'e verjaardag. Tijd voor een felicitatie!',
                        'url' => route('mijncn.module', 'birthdays'),
                    ]),
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            }

            if ($birthdayUser->birthday_visibility === 'community') {
                $this->sendDiscord('verjaardagen', [
                    'content' => $birthdayUser->discord_id
                        ? 'Gefeliciteerd <@'.$birthdayUser->discord_id.'>!'
                        : 'Gefeliciteerd '.$birthdayUser->name.'!',
                    'embeds' => [[
                        'title' => 'Vandaag is '.$birthdayUser->name.' jarig',
                        'description' => 'CN Community wenst '.$birthdayUser->name.' een geweldige '.$age.'e verjaardag. Samen zijn we één.',
                        'color' => 14883619,
                    ]],
                ]);
            }
            $processed++;
        }

        return $processed;
    }

    private function announceAwardRound(AwardRound $round): void
    {
        if (!in_array($round->type, ['nomination', 'public_vote'], true)) {
            return;
        }

        $isMini = $round->edition->type === 'mini_awards';
        $action = $round->type === 'nomination' ? 'Nominaties zijn geopend' : 'De stemronde is geopend';
        $url = $isMini ? route('mini.awards') : route('awards');

        $purpose = $round->type === 'public_vote' ? 'stem-nu' : 'awards-info';

        $this->sendDiscord($purpose, [
            'embeds' => [[
                'title' => $action.' voor '.$round->edition->name,
                'description' => $round->type === 'nomination'
                    ? 'Geef iemand uit de community het podium dat diegene verdient.'
                    : 'Bekijk de kandidaten en breng per categorie jouw stem uit.',
                'url' => $url,
                'color' => 14883619,
                'fields' => [[
                    'name' => 'Geopend tot',
                    'value' => $round->ends_at->translatedFormat('d F Y \o\m H:i'),
                    'inline' => true,
                ]],
            ]],
        ]);
    }

    private function claim(string $key, string $type, array $payload): bool
    {
        if (!Schema::hasTable('automation_logs')) {
            return false;
        }

        return AutomationLog::query()->insertOrIgnore([
            'key' => $key,
            'type' => $type,
            'payload' => json_encode($payload),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    private function sendDiscord(string $purpose, array $payload): void
    {
        $channel = $this->discordChannel($purpose);
        $delivery = null;

        if ($channel && Schema::hasTable('discord_deliveries')) {
            $delivery = DiscordDelivery::create([
                'discord_channel_id' => $channel->id,
                'event' => $purpose,
                'payload' => $payload,
                'status' => 'pending',
            ]);
        }

        try {
            if ($channel && ctype_digit($channel->discord_channel_id)) {
                $this->discord->sendChannelMessage($channel->discord_channel_id, $payload);
            } else {
                $this->discord->sendWebhook($payload, $channel?->webhook_url);
            }
            $delivery?->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $exception) {
            $delivery?->update([
                'status' => 'failed',
                'response' => Str::limit($exception->getMessage(), 1000),
            ]);
            Log::warning('Community automation Discord notification failed.', [
                'purpose' => $purpose,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function discordChannel(string $purpose): ?DiscordChannel
    {
        if (!Schema::hasTable('discord_channels')) {
            return null;
        }

        return DiscordChannel::where('purpose', $purpose)
            ->where('is_active', true)
            ->first();
    }
}
