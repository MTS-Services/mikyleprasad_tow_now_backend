<?php

return [

    'enabled_codes' => array_values(array_filter(array_map(
        static fn (string $c): string => strtoupper(trim($c)),
        explode(',', (string) env('APP_ENABLED_CURRENCIES', 'USD,EUR,SAR'))
    ))),

    'base_currency' => strtoupper((string) env('APP_BASE_CURRENCY', 'USD')),

    'currency_override_header' => env('APP_CURRENCY_HEADER', 'X-App-Currency'),

    'currency_override_enabled' => (bool) env('APP_CURRENCY_HEADER_ENABLED', true),

    'cache_ttl' => (int) env('CURRENCY_CACHE_TTL', 120),

    'rate_min' => (float) env('CURRENCY_RATE_MIN', 1e-10),

    'rate_max' => (float) env('CURRENCY_RATE_MAX', 1e10),

    'fx_sync' => [
        'enabled' => (bool) env('CURRENCY_FX_SYNC_ENABLED', true),
        'allowed_hosts' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('CURRENCY_FX_ALLOWED_HOSTS', 'api.frankfurter.app'))
        ))),
        'timeout_seconds' => (int) env('CURRENCY_FX_TIMEOUT', 10),
    ],

];
