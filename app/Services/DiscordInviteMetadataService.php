<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DiscordInviteMetadataService
{
    public function enrich(?string $url): array
    {
        $code = $this->inviteCode($url);
        if (!$code) {
            return [];
        }

        try {
            $invite = Http::timeout(8)
                ->acceptJson()
                ->get('https://discord.com/api/v10/invites/'.$code, [
                    'with_counts' => 'true',
                    'with_expiration' => 'true',
                ])
                ->throw()
                ->json();
        } catch (\Throwable $exception) {
            Log::warning('Discord invite metadata lookup failed.', [
                'invite' => $url,
                'message' => $exception->getMessage(),
            ]);

            return ['discord_invite' => $url];
        }

        $guild = $invite['guild'] ?? [];
        $guildId = (string) ($guild['id'] ?? '');

        return array_filter([
            'discord_invite' => $url,
            'discord_guild_id' => $guildId ?: null,
            'name' => $guild['name'] ?? null,
            'description' => $guild['description'] ?? null,
            'logo_url' => $this->cdnAsset('icons', $guildId, $guild['icon'] ?? null, 256),
            'banner_url' => $this->cdnAsset('banners', $guildId, $guild['banner'] ?? null, 1024)
                ?: $this->cdnAsset('splashes', $guildId, $guild['splash'] ?? null, 1024),
            'member_count' => $invite['approximate_member_count'] ?? null,
            'online_count' => $invite['approximate_presence_count'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function inviteCode(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $value = trim($url);
        if (preg_match('~(?:discord\.gg|discord(?:app)?\.com/invite)/([A-Za-z0-9_-]+)~i', $value, $matches)) {
            return $matches[1];
        }

        if (!Str::contains($value, ['/', ' ']) && preg_match('~^[A-Za-z0-9_-]{3,}$~', $value)) {
            return $value;
        }

        return null;
    }

    private function cdnAsset(string $type, string $guildId, ?string $hash, int $size): ?string
    {
        if ($guildId === '' || !$hash) {
            return null;
        }

        $extension = str_starts_with($hash, 'a_') ? 'gif' : 'png';

        return "https://cdn.discordapp.com/{$type}/{$guildId}/{$hash}.{$extension}?size={$size}";
    }
}
