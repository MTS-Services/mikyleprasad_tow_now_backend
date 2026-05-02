<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Cloud Translation Credentials (v2 & v3)
    |--------------------------------------------------------------------------
    |
    | For the latest Google Cloud Translation v3 API, service account
    | credentials are strongly recommended. Provide the absolute path to
    | your JSON key file via GOOGLE_TRANSLATE_CREDENTIALS.
    |
    |   GOOGLE_TRANSLATE_PROJECT_ID=your-project-id
    |   GOOGLE_TRANSLATE_LOCATION=global
    |   GOOGLE_TRANSLATE_CREDENTIALS=/absolute/path/to/key.json
    |   GOOGLE_TRANSLATE_CREDENTIALS=storage/sourcenest-translation.json
    |
    | Do not use a leading slash with "storage" (e.g. /storage/key.json points at
    | the OS root, not Laravel's storage/ directory). Use storage/... or an
    | absolute path.
    |
    | The legacy v2 API-key flow is still supported for backwards
    | compatibility via GOOGLE_TRANSLATE_API_KEY, but new setups should
    | prefer service accounts.
    */
    'google_project_id' => env('GOOGLE_TRANSLATE_PROJECT_ID'),
    'google_location' => env('GOOGLE_TRANSLATE_LOCATION', 'global'),
    'google_credentials' => env('GOOGLE_TRANSLATE_CREDENTIALS'),
    'google_api_key' => env('GOOGLE_TRANSLATE_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Google Translate Mode
    |--------------------------------------------------------------------------
    |
    | - auto (default): try v3 first (service account), fall back to v2 if API key exists
    | - v3: always use Cloud Translation v3 (requires service account credentials)
    | - v2: always use REST v2 endpoints with API key (no JWT/OAuth user login)
    */
    'google_mode' => env('GOOGLE_TRANSLATE_MODE', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Default Source Locale
    |--------------------------------------------------------------------------
    | The BCP-47 locale your application stores native content in.
    | Auto-detection will override this on a per-request basis when enabled.
    */
    'source_locale' => env('TRANSLATION_SOURCE_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Auto-detect Source Language
    |--------------------------------------------------------------------------
    | When true, Google detects the submitted language before translating.
    | This costs one extra API detect call per job.
    */
    'auto_detect' => env('TRANSLATION_AUTO_DETECT', true),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    | Each translated string is cached by a hash of its source text + locale.
    | Use Redis in production (TRANSLATION_CACHE_STORE=redis).
    */
    'cache' => [
        'enabled' => env('TRANSLATION_CACHE_ENABLED', true),
        'store' => env('TRANSLATION_CACHE_STORE', 'default'),
        'ttl' => env('TRANSLATION_CACHE_TTL', 604800), // 7 days
        'prefix' => 'gct',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | When enabled, translations are dispatched to the queue (recommended).
    | Set TRANSLATION_QUEUE_ENABLED=false for synchronous translation (testing).
    */
    'queue' => [
        'enabled' => env('TRANSLATION_QUEUE_ENABLED', true),
        'connection' => env('TRANSLATION_QUEUE_CONNECTION', 'default'),
        'name' => env('TRANSLATION_QUEUE_NAME', 'translations'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Batch Size
    |--------------------------------------------------------------------------
    | Google Translate v2 accepts up to 128 strings per request.
    | Google Cloud Translation v3 also enforces limits on the number of
    | texts and total payload size. Keep this at a safe value such as 100.
    */
    'batch_size' => (int) env('TRANSLATION_BATCH_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | V3 Advanced Options
    |--------------------------------------------------------------------------
    |
    | Optional configuration for Google Cloud Translation v3. These are
    | entirely optional — if left null, the default general NMT model is
    | used and no glossaries are applied.
    |
    | Example model:
    |   projects/your-project/locations/global/models/general/nmt
    |
    | Example glossary:
    |   projects/your-project/locations/global/glossaries/your-glossary
    */
    'v3' => [
        'model' => env('GOOGLE_TRANSLATE_V3_MODEL'),
        'glossary' => env('GOOGLE_TRANSLATE_V3_GLOSSARY'),
    ],

];
