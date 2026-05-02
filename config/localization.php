<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | Comma-separated list in APP_SUPPORTED_LOCALES (e.g. "en,es"). Requests
    | resolve to one of these via Accept-Language or the optional override header.
    |
    */

    'supported_locales' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('APP_SUPPORTED_LOCALES', 'en,es,ar,he'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Optional locale override header
    |--------------------------------------------------------------------------
    |
    | When enabled, a client may send this header with a supported locale code.
    | Unknown values are ignored (Accept-Language is used instead).
    |
    */

    'locale_override_header' => env('APP_LOCALE_HEADER', 'X-App-Locale'),

    'locale_override_enabled' => (bool) env('APP_LOCALE_HEADER_ENABLED', true),

];
