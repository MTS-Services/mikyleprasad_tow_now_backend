<?php

namespace App\Services\Translation;

use App\Models\Language;
use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * TranslationOrchestrator
 *
 * Coordinates the full translation pipeline:
 *   1. Resolve which target locales need to be updated
 *   2. Optionally detect the source language via Google API
 *   3. For each target locale, call Google Translate with the full field batch
 *   4. Bulk-upsert all translation rows in a single DB round-trip per locale
 *
 * Zero N+1:
 *   - Languages are served from the in-memory/cache via Language::allActive()
 *   - All translation rows are written via a single upsert per-model
 *
 * API efficiency:
 *   - One Google API call per target locale (batching all fields)
 *   - Results are cached by GoogleTranslationService
 */
final class TranslationOrchestrator
{
    public function __construct(
        private readonly GoogleTranslationService $translator,
    ) {}

    /**
     * Translate all given source fields into every active target language
     * and persist the results.
     *
     * @param  Model&HasTranslations  $model  The Eloquent model instance
     * @param  array<string, string>  $sourceData  ['field' => 'source text', ...]
     * @param  string|null  $sourceLocale  Provided or will be auto-detected
     */
    public function handle(
        Model $model,
        array $sourceData,
        ?string $sourceLocale = null,
        ?string $modelUpdatedAtSnapshot = null
    ): void {
        if (! in_array(HasTranslations::class, class_uses_recursive($model))) {
            throw new \InvalidArgumentException(
                class_basename($model).' must use the HasTranslations trait.'
            );
        }

        // Filter to only the fields the model declares as translatable
        $translatableFields = $model->translatableFields();
        $sourceData = array_intersect_key(
            $sourceData,
            array_flip($translatableFields)
        );

        if (empty($sourceData)) {
            Log::debug('TranslationOrchestrator: no translatable fields in source data.', [
                'model' => class_basename($model),
                'id' => $model->getKey(),
            ]);

            return;
        }

        if ($modelUpdatedAtSnapshot !== null) {
            $model->refresh();
            $currentUpdatedAt = $model->updated_at?->toIso8601String();

            if ($currentUpdatedAt !== $modelUpdatedAtSnapshot) {
                Log::info('TranslationOrchestrator: stale translation job skipped.', [
                    'model' => class_basename($model),
                    'id' => $model->getKey(),
                    'snapshot_updated_at' => $modelUpdatedAtSnapshot,
                    'current_updated_at' => $currentUpdatedAt,
                ]);

                return;
            }
        }

        // Detect source locale if not provided
        $resolvedSource = $sourceLocale ?? $this->resolveSourceLocale($sourceData);

        // Get all active locales except the source
        $targets = Language::translationTargets($resolvedSource);

        if ($targets->isEmpty()) {
            Log::debug('TranslationOrchestrator: no active target languages configured.');

            return;
        }

        $translatedByLocale = $this->translateIntoAllTargets(
            $sourceData,
            $resolvedSource,
            $targets
        );

        // Also ensure the source locale row exists so look-ups are consistent
        $translatedByLocale[$resolvedSource] = $sourceData;

        // Single upsert call per model (no N+1)
        $model->upsertTranslations($translatedByLocale);

        Log::info('TranslationOrchestrator: translations persisted.', [
            'model' => class_basename($model),
            'id' => $model->getKey(),
            'source' => $resolvedSource,
            'targets' => $targets->pluck('locale')->all(),
        ]);
    }

    /* ------------------------------------------------------------------
    |  Private helpers
    | ------------------------------------------------------------------ */

    /**
     * @param  array<string, string>  $sourceData
     * @param  Collection<int, Language>  $targets
     * @return array<string, array<string, string>> keyed by locale
     */
    private function translateIntoAllTargets(
        array $sourceData,
        string $resolvedSource,
        Collection $targets
    ): array {
        $results = [];

        foreach ($targets as $language) {
            try {
                $translated = $this->translator->translateBatch(
                    $sourceData,
                    $language->locale,
                    $resolvedSource
                );

                $results[$language->locale] = $translated;
            } catch (\Throwable $e) {
                // Do not let one failing locale abort all others
                Log::error('TranslationOrchestrator: failed to translate into locale.', [
                    'locale' => $language->locale,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Use the first non-empty field to detect the source language.
     *
     * @param  array<string, string>  $sourceData
     */
    private function resolveSourceLocale(array $sourceData): string
    {
        if (! config('translation.auto_detect', true)) {
            return config('translation.source_locale', 'en');
        }

        // Use the first non-empty value for detection (cheaper than detecting all)
        $sampleText = collect($sourceData)->first(fn ($v) => ! empty(trim($v)));

        if ($sampleText === null) {
            return config('translation.source_locale', 'en');
        }

        return $this->translator->detectLanguage($sampleText);
    }
}
