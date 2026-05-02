<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Timezone Override
    |--------------------------------------------------------------------------
    |
    | Allows clients to request a specific output timezone per request.
    | Priority order is handled in RequestTimezoneResolver:
    | header override -> authenticated user's saved timezone -> app default.
    |
    */

    'timezone_override_enabled' => env('APP_TIMEZONE_OVERRIDE_ENABLED', true),

    'timezone_override_header' => env('APP_TIMEZONE_HEADER', 'X-App-Timezone'),

    'accept_timezone_header' => env('APP_ACCEPT_TIMEZONE_HEADER', 'Accept-Timezone'),
];
