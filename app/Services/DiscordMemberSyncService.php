<?php

namespace App\Services;

use App\Models\DiscordMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DiscordMemberSyncService
{
    public function __construct(private readonly DiscordService $discord)
    {
    }

    public function sync(): int
    {
        $token = config('services.discord.bot_token');
        $guildId = config('services.discord.guild_id');
        if (!$token || !$guildId) {
            throw new RuntimeException('Discord bot-token of guild-ID ontbreekt.');
        }

        $syncedAt = now();
        $after = '0';
        $total = 0;

        do {
            $response = Http::withHeaders(['Authorization' => 'Bot '.$token])
                ->timeout(30)
                ->get("https://discord.com/api/v10/guilds/{$guildId}/members", [
                    'limit' => 1000,
                    'after' => $after,
                ]);

            if ($response->failed()) {
                throw new RuntimeException('Discord leden synchroniseren mislukt: HTTP '.$response->status().'. Controleer de Server Members Intent.');
            }

            $members = $response->json();
            foreach ($members as $member) {
                $user = $member['user'] ?? [];
                if (!empty($user['id'])) {
                    $after = (string) $user['id'];
                }
                if (($user['bot'] ?? false) || empty($user['id'])) {
                    continue;
                }

                $this->storeMember($member, $syncedAt);
                $total++;
            }
        } while (count($members) === 1000);

        DiscordMember::where('synced_at', '<', $syncedAt)->update(['is_active' => false]);

        return $total;
    }

    public function storeMember(array $member, $syncedAt = null): DiscordMember
    {
        $user = $member['user'] ?? [];
        $discordId = (string) ($user['id'] ?? '');
        if ($discordId === '') {
            throw new RuntimeException('Discord-lid bevat geen gebruikers-ID.');
        }

        return DB::transaction(fn () => DiscordMember::updateOrCreate(
            ['discord_id' => $discordId],
            [
                'username' => $user['username'] ?? 'onbekend',
                'display_name' => $member['nick'] ?? $user['global_name'] ?? $user['username'] ?? 'Onbekend lid',
                'avatar' => $user['avatar'] ?? null,
                'platform_role' => $this->discord->platformRole($member)->value,
                'roles' => array_values(array_map('strval', $member['roles'] ?? [])),
                'joined_at' => $member['joined_at'] ?? null,
                'is_bot' => (bool) ($user['bot'] ?? false),
                'is_active' => true,
                'synced_at' => $syncedAt ?: now(),
            ]
        ));
    }
}
