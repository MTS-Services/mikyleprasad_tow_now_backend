<?php

declare(strict_types=1);

/**
 * Kreait registers one entry per key under `projects`. `default` must match a key here.
 * Set FIREBASE_PROJECT to your Firebase/Google Cloud project id (same as in the service account JSON);
 * credentials path stays in FIREBASE_CREDENTIALS regardless of project id.
 */
$defaultProject = trim((string) env('FIREBASE_PROJECT', 'app'));
if ($defaultProject === '') {
    $defaultProject = 'app';
}

$projectConfig = [
    'credentials' => env('FIREBASE_CREDENTIALS', env('GOOGLE_APPLICATION_CREDENTIALS')),

    'auth' => [
        'tenant_id' => env('FIREBASE_AUTH_TENANT_ID'),
    ],

    'firestore' => [],

    'database' => [
        'url' => env('FIREBASE_DATABASE_URL'),
    ],

    'storage' => [
        'default_bucket' => env('FIREBASE_STORAGE_DEFAULT_BUCKET'),
    ],

    'cache_store' => env('FIREBASE_CACHE_STORE', 'file'),

    'logging' => [
        'http_log_channel' => env('FIREBASE_HTTP_LOG_CHANNEL'),
        'http_debug_log_channel' => env('FIREBASE_HTTP_DEBUG_LOG_CHANNEL'),
    ],

    'http_client_options' => [
        'proxy' => env('FIREBASE_HTTP_CLIENT_PROXY'),
        'timeout' => env('FIREBASE_HTTP_CLIENT_TIMEOUT'),
        'guzzle_middlewares' => [],
    ],
];

return [
    'default' => $defaultProject,

    'projects' => [
        $defaultProject => $projectConfig,
    ],
];
