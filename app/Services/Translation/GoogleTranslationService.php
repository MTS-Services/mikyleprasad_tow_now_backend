<?php

namespace App\Services\Translation;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Cloud\Translate\V3\Client\TranslationServiceClient;
use Google\Cloud\Translate\V3\DetectLanguageRequest;
use Google\Cloud\Translate\V3\TranslateTextGlossaryConfig;
use Google\Cloud\Translate\V3\TranslateTextRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cache-aware wrapper around Google Cloud Translation v3.
 *
 * Responsibilities:
 *   - Authenticate via service account credentials or API key (from config)
 *   - Batch all translatable fields into ONE API call per target locale
 *   - Cache each result by a hash of the source text + target locale
 *   - Detect source language when auto_detect is enabled
 *   - Log errors server-side; never expose key material in responses
 */
final class GoogleTranslationService
{
    private ?TranslationServiceClient $client = null;

    private ?string $parent = null;

    private ?string $apiKey = null;

    /**
     * googleMode:
     * - v3: use TranslationServiceClient (service account recommended)
     * - v2: use REST v2 endpoints with API key (no JWT/OAuth user login)
     * - auto (default): try v3 first, fall back to v2 if api key exists
     */
    private string $googleMode;

    public function __construct()
    {
        // Some .env files mistakenly include quotes like GOOGLE_TRANSLATE_MODE='v2'.
        // Normalize to a plain token: auto|v2|v3
        $this->googleMode = trim((string) config('translation.google_mode', 'auto'), " \t\n\r\0\x0B'\"");

        $projectId = config('translation.google_project_id');
        $location = config('translation.google_location', 'global');
        $credentials = config('translation.google_credentials');
        $apiKey = config('translation.google_api_key');

        if (empty($credentials) && empty($apiKey)) {
            throw new RuntimeException(
                'Google Cloud Translation credentials are not configured. '
                .'Set GOOGLE_TRANSLATE_CREDENTIALS (service account JSON path) '
                .'or GOOGLE_TRANSLATE_API_KEY in your .env file.'
            );
        }

        $this->apiKey = ! empty($apiKey) ? (string) $apiKey : null;

        if ($this->googleMode === 'v2') {
            if ($this->apiKey === null) {
                throw new RuntimeException(
                    'translation.google_mode=v2 requires GOOGLE_TRANSLATE_API_KEY to be set.'
                );
            }

            return;
        }

        $options = [];

        if (! empty($credentials)) {
            if (empty($projectId)) {
                throw new RuntimeException(
                    'Google Cloud Translation project ID is not configured. '
                    .'Set GOOGLE_TRANSLATE_PROJECT_ID in your .env file.'
                );
            }

            $keyFilePath = self::resolveKeyFilePath($credentials);
            $json = file_get_contents($keyFilePath);

            if ($json === false) {
                throw new RuntimeException('Google Cloud credentials JSON file could not be read.');
            }

            $keyFile = json_decode($json, true);
            if (! is_array($keyFile)) {
                throw new RuntimeException('Google Cloud credentials JSON file is not valid JSON.');
            }

            $options['credentials'] = new ServiceAccountCredentials(
                TranslationServiceClient::$serviceScopes,
                $keyFile
            );
        }

        if (! empty($options)) {
            $this->client = new TranslationServiceClient($options);
            $this->parent = TranslationServiceClient::locationName($projectId, $location);
        } elseif ($this->googleMode === 'v3') {
            throw new RuntimeException(
                'translation.google_mode=v3 requires GOOGLE_TRANSLATE_CREDENTIALS to be set.'
            );
        }
    }

    /**
     * Resolve GOOGLE_TRANSLATE_CREDENTIALS to a real filesystem path.
     *
     * Common mistake: setting `/storage/key.json` (OS root) instead of Laravel's
     * storage directory. Accepts absolute paths, `storage/...`, and `/storage/...`.
     */
    private static function resolveKeyFilePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new RuntimeException('Google Cloud credentials path is empty.');
        }

        if (is_file($path)) {
            return $path;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($normalized, 'storage/')) {
            $relative = substr($normalized, strlen('storage/'));
            $candidate = storage_path($relative);
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $candidate = base_path($normalized);
        if (is_file($candidate)) {
            return $candidate;
        }

        throw new RuntimeException(
            'Google Cloud credentials JSON file could not be found. '
            .'Use a full path to the key file, or a project-relative path such as '
            .'storage/sourcenest-translation.json. '
            .'Configured value was: '.$path
        );
    }

    /**
     * Translate a keyed array of strings into one target locale.
     *
     * Returns an array with the same keys, values being translated strings.
     *
     * @param  array<string, string>  $texts  ['field_key' => 'source text', ...]
     * @param  string  $targetLocale  BCP-47 code e.g. "ar", "zh-CN"
     * @param  string|null  $sourceLocale  null = let Google auto-detect
     * @return array<string, string>
     */
    public function translateBatch(
        array $texts,
        string $targetLocale,
        ?string $sourceLocale = null
    ): array {
        if (empty($texts)) {
            return [];
        }

        $keys = array_keys($texts);
        $values = array_values($texts);

        // Respect Google's 128-strings-per-request limit
        $batchSize = (int) config('translation.batch_size', 100);
        $valueChunks = array_chunk($values, $batchSize);
        $keyChunks = array_chunk($keys, $batchSize);

        $results = [];

        foreach ($valueChunks as $chunkIdx => $chunkValues) {
            $chunkKeys = $keyChunks[$chunkIdx];
            $uncachedKeys = [];
            $uncachedValues = [];

            // 1. Serve cached results immediately
            foreach ($chunkValues as $i => $text) {
                $cacheKey = $this->cacheKey($text, $targetLocale, $sourceLocale);

                if (
                    config('translation.cache.enabled')
                    && Cache::store(config('translation.cache.store'))->has($cacheKey)
                ) {
                    $results[$chunkKeys[$i]] = Cache::store(config('translation.cache.store'))->get($cacheKey);
                } else {
                    $uncachedKeys[] = $chunkKeys[$i];
                    $uncachedValues[] = $text;
                }
            }

            if (empty($uncachedValues)) {
                continue;
            }

            // 2. One API call for the remaining strings
            $translated = $this->callApi($uncachedValues, $targetLocale, $sourceLocale);

            // 3. Map back to field keys and write to cache
            foreach ($translated as $i => $translatedText) {
                $fieldKey = $uncachedKeys[$i];
                $origText = $uncachedValues[$i];

                $results[$fieldKey] = $translatedText;

                if (config('translation.cache.enabled')) {
                    Cache::store(config('translation.cache.store'))->put(
                        $this->cacheKey($origText, $targetLocale, $sourceLocale),
                        $translatedText,
                        config('translation.cache.ttl')
                    );
                }
            }
        }

        return $results;
    }

    /**
     * Detect the BCP-47 language code of a text string.
     * Falls back to the configured source locale on failure.
     */
    public function detectLanguage(string $text): string
    {
        if ($this->googleMode === 'v2') {
            return $this->detectLanguageV2($text);
        }

        try {
            if ($this->client === null || $this->parent === null) {
                throw new RuntimeException('Google Translation v3 client is not configured.');
            }

            $request = new DetectLanguageRequest([
                'parent' => $this->parent,
                'content' => $text,
                'mime_type' => 'text/plain',
            ]);

            $response = $this->client->detectLanguage($request);

            $languages = iterator_to_array($response->getLanguages());

            if ($languages === []) {
                return config('translation.source_locale', 'en');
            }

            $primary = $languages[0];

            return $primary->getLanguageCode() ?: config('translation.source_locale', 'en');
        } catch (\Throwable $e) {
            if ($this->googleMode === 'auto' && $this->apiKey !== null) {
                return $this->detectLanguageV2($text);
            }

            Log::warning('GoogleTranslationService::detectLanguage failed.', [
                'error' => $e->getMessage(),
            ]);

            return config('translation.source_locale', 'en');
        }
    }

    /* ------------------------------------------------------------------
    |  Private helpers
    | ------------------------------------------------------------------ */

    /**
     * @param  string[]  $texts  Plain ordered array of source strings
     * @return string[] Translated strings in the same order
     */
    private function callApi(array $texts, string $targetLocale, ?string $sourceLocale): array
    {
        if ($this->googleMode === 'v2') {
            return $this->translateBatchV2($texts, $targetLocale, $sourceLocale);
        }

        try {
            if ($this->client === null || $this->parent === null) {
                throw new RuntimeException('Google Translation v3 client is not configured.');
            }

            $model = config('translation.v3.model');
            $glossary = config('translation.v3.glossary');

            $request = TranslateTextRequest::build($this->parent, $targetLocale, $texts);

            $request->setMimeType('text/plain');

            if ($sourceLocale !== null) {
                $request->setSourceLanguageCode($sourceLocale);
            }

            if (! empty($model)) {
                $request->setModel($model);
            }

            if (! empty($glossary)) {
                $request->setGlossaryConfig(new TranslateTextGlossaryConfig([
                    'glossary' => $glossary,
                ]));
            }

            $response = $this->client->translateText($request);

            $translations = [];

            foreach ($response->getTranslations() as $translation) {
                $translations[] = $translation->getTranslatedText();
            }

            return $translations;
        } catch (\Throwable $e) {
            if ($this->googleMode === 'auto' && $this->apiKey !== null) {
                return $this->translateBatchV2($texts, $targetLocale, $sourceLocale);
            }

            // Log without surfacing internal details or the API key
            Log::error('GoogleTranslationService::translateBatch API call failed.', [
                'target' => $targetLocale,
                'source' => $sourceLocale,
                'count' => count($texts),
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Translation API request failed. Please try again later.',
                previous: $e
            );
        }
    }

    private function detectLanguageV2(string $text): string
    {
        if ($this->apiKey === null) {
            return config('translation.source_locale', 'en');
        }

        try {
            $url = 'https://translation.googleapis.com/language/translate/v2/detect?key='.$this->apiKey;

            $response = Http::asJson()
                ->timeout(20)
                ->post($url, [
                    'q' => $text,
                ])
                ->throw()
                ->json();

            $language = $response['data']['detections'][0][0]['language'] ?? null;

            return is_string($language) && $language !== ''
                ? $language
                : config('translation.source_locale', 'en');
        } catch (\Throwable $e) {
            Log::warning('GoogleTranslationService::detectLanguage v2 failed.', [
                'key_hash' => hash('xxh3', $this->apiKey),
                'error' => $e->getMessage(),
            ]);

            return config('translation.source_locale', 'en');
        }
    }

    /**
     * @param  string[]  $texts
     * @return string[]
     */
    private function translateBatchV2(array $texts, string $targetLocale, ?string $sourceLocale): array
    {
        if ($this->apiKey === null) {
            throw new RuntimeException('Google Translate API key is not configured.');
        }

        try {
            $url = 'https://translation.googleapis.com/language/translate/v2?key='.$this->apiKey;

            $payload = [
                'q' => $texts,
                'target' => $targetLocale,
                'format' => 'text',
            ];

            if ($sourceLocale !== null) {
                $payload['source'] = $sourceLocale;
            }

            $response = Http::asJson()
                ->timeout(30)
                ->post($url, $payload)
                ->throw()
                ->json();

            $translations = $response['data']['translations'] ?? [];

            if (! is_array($translations)) {
                throw new RuntimeException('Unexpected Google Translate v2 response.');
            }

            return array_map(
                fn ($t) => (string) ($t['translatedText'] ?? ''),
                $translations
            );
        } catch (\Throwable $e) {
            Log::error('GoogleTranslationService::translateBatch v2 failed.', [
                'target' => $targetLocale,
                'source' => $sourceLocale,
                'count' => count($texts),
                'key_hash' => $this->apiKey ? hash('xxh3', $this->apiKey) : null,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Translation API request failed. Please try again later.',
                previous: $e
            );
        }
    }

    /**
     * Build a safe, length-bounded cache key.
     * Uses xxh3 (PHP 8.1+) for fast, collision-resistant hashing.
     */
    private function cacheKey(string $text, string $target, ?string $source): string
    {
        $prefix = config('translation.cache.prefix', 'gct');
        $hash = hash('xxh3', $text);

        return implode(':', array_filter([$prefix, $source, $target, $hash]));
    }
}
