<?php

return [
    'external_enabled' => env('EXTERNAL_NEWS_ENABLED', true),
    'refresh_minutes' => (int) env('EXTERNAL_NEWS_REFRESH_MINUTES', 30),
    'max_per_feed' => (int) env('EXTERNAL_NEWS_MAX_PER_FEED', 5),
    'feeds' => [
        [
            'source' => 'NU.nl',
            'url' => env('NU_RSS_FEED', 'https://www.nu.nl/rss/Algemeen'),
            'category' => 'Nederlands nieuws',
        ],
        [
            'source' => 'NOS',
            'url' => env('NOS_RSS_FEED', 'https://feeds.nos.nl/nosnieuwsalgemeen'),
            'category' => 'Nederlands nieuws',
        ],
    ],
];
