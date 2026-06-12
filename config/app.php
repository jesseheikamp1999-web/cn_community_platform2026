<?php

return [
    'name' => env('APP_NAME', 'CN Community Platform 2026'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'Europe/Amsterdam'),
    'locale' => env('APP_LOCALE', 'nl'),
    'fallback_locale' => 'nl',
    'faker_locale' => 'nl_NL',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
    'maintenance' => ['driver' => 'file', 'store' => 'database'],
];
