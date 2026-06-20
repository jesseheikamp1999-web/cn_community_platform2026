<?php

namespace App\Services;

use App\Enums\UserRole;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DiscordService
{
    public function exchangeCode(string $code, ?string $redirectUri = null): array
    {
        return Http::asForm()->post('https://discord.com/api/v10/oauth2/token', [
            'client_id' => config('services.discord.client_id'),
            'client_secret' => config('services.discord.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri ?: config('services.discord.redirect'),
        ])->throw()->json();
    }

    public function user(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->get('https://discord.com/api/v10/users/@me')
            ->throw()
            ->json();
    }

    public function guildMember(string $discordId): ?array
    {
        $token = config('services.discord.bot_token');
        $guildId = config('services.discord.guild_id');
        if (!$token || !$guildId) {
            return null;
        }

        try {
            return Http::withHeaders(['Authorization' => 'Bot '.$token])
                ->get("https://discord.com/api/v10/guilds/{$guildId}/members/{$discordId}")
                ->throw()
                ->json();
        } catch (\Throwable $exception) {
            Log::warning('Discord guild role sync failed.', [
                'discord_id' => $discordId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function platformRole(?array $member): UserRole
    {
        $memberRoles = array_map('strval', $member['roles'] ?? []);
        $configured = config('services.discord.roles', []);
        $priority = [
            'owner' => UserRole::Owner,
            'management' => UserRole::Management,
            'admin' => UserRole::Admin,
            'moderator' => UserRole::Moderator,
            'helper' => UserRole::Helper,
            'jury' => UserRole::Jury,
            'member' => UserRole::Member,
        ];

        foreach ($priority as $key => $role) {
            $roleId = (string) ($configured[$key] ?? '');
            if ($roleId !== '' && in_array($roleId, $memberRoles, true)) {
                return $role;
            }
        }

        return UserRole::Member;
    }

    public function sendWebhook(array $payload, ?string $url = null): void
    {
        $webhook = $url ?: config('services.discord.webhook_url');
        if (!$webhook) {
            throw new RuntimeException('Geen Discord-webhook geconfigureerd.');
        }

        Http::post($webhook, $payload)->throw();
    }

    public function sendChannelMessage(string $channelId, array $payload): void
    {
        $token = config('services.discord.bot_token');
        if (!$token) {
            throw new RuntimeException('Geen Discord bot token geconfigureerd.');
        }

        if ($channelId === '' || !ctype_digit($channelId)) {
            throw new RuntimeException('Geen geldig Discord kanaal-ID ingesteld.');
        }

        Http::withHeaders(['Authorization' => 'Bot '.$token])
            ->post("https://discord.com/api/v10/channels/{$channelId}/messages", $payload)
            ->throw();
    }
}
