<?php

return [
    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI'),
        'bot_token' => env('DISCORD_BOT_TOKEN'),
        'guild_id' => env('DISCORD_GUILD_ID'),
        'webhook_url' => env('DISCORD_WEBHOOK_URL'),
        'roles' => [
            'member' => env('DISCORD_ROLE_MEMBER'),
            'helper' => env('DISCORD_ROLE_HELPER'),
            'moderator' => env('DISCORD_ROLE_MODERATOR'),
            'admin' => env('DISCORD_ROLE_ADMIN'),
            'management' => env('DISCORD_ROLE_MANAGEMENT'),
            'owner' => env('DISCORD_ROLE_OWNER'),
            'jury' => env('DISCORD_ROLE_JURY'),
            'wk_champion_2026' => env('DISCORD_ROLE_WK_CHAMPION_2026'),
        ],
    ],
    'community' => [
        'discord_invite' => env('CN_DISCORD_INVITE'),
        'twitch_url' => env('CN_TWITCH_URL'),
    ],
    'discord_sync' => [
        'api_key' => env('DISCORD_SYNC_API_KEY'),
    ],
    'nomi' => [
        'endpoint' => env('NOMI_AI_ENDPOINT'),
        'token' => env('NOMI_AI_TOKEN'),
    ],
];
