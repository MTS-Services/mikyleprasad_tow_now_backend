<?php

namespace App\Traits;

use App\Models\Language;
use App\Services\Translation\TranslationOrchestrator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * HasTranslations
 *
 * Drop onto any Eloquent model that needs multi-language content.
 *
 * The model MUST implement:
 *
 *   protected function translationModelClass(): string   // e.g. ProductTranslation::class
 *   public function translatableFields(): array          // e.g. ['name', 'description']
 *
 * Optional — override the FK if convention doesn't match:
 *   protected string $translationForeignKey = 'product_id';
 *
 * The translation table MUST have:
 *   - A `locale` column  (string, 10)
 *   - A column for every field in translatableFields()
 *   - A unique index on [parent_fk, locale]
 */
trait HasTranslations
{
    /* ------------------------------------------------------------------
    |  Contract — implement on the model
    | ------------------------------------------------------------------ */

    abstract protected function translationModelClass(): string;

    /** @return string[] */
    abstract public function translatableFields(): array;

    /* ------------------------------------------------------------------
    |  Relationship
    | ------------------------------------------------------------------ */

    /**
     * Provides the standard translations() HasMany relation.
     * Eager-load with ->with('translations') to avoid N+1.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(
            $this->translationModelClass(),
            $this->inferTranslationForeignKey(),
            $this->getKeyName()
        );
    }

    /* ------------------------------------------------------------------
    |  Read helpers
    | ------------------------------------------------------------------ */

    /**
     * Get one translated field for the given locale, with fallback.
     *
     * Falls back:
     *   1. Requested locale translation row
     *   2. Fallback locale translation row
     *   3. Column on the parent model itself
     *
     * Assumes 'translations' is already eager-loaded. Lazy-loads otherwise.
     */
    public function translated(
        string $field,
        ?string $locale = null,
        ?string $fallbackLocale = null
    ): mixed {
        $locale = $locale ?? app()->getLocale();
        $fallbackLocale = $fallbackLocale ?? config('translation.source_locale', 'en');

        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        $primary = $translations->firstWhere('locale', $locale);
        if ($primary && ! empty($primary->{$field})) {
            return $primary->{$field};
        }

        if ($locale !== $fallbackLocale) {
            $fallback = $translations->firstWhere('locale', $fallbackLocale);
            if ($fallback && ! empty($fallback->{$field})) {
                return $fallback->{$field};
            }
        }

        return $this->{$field} ?? null;
    }

    /**
     * Get all translatable fields for a locale, keyed by field name.
     *
     * @return array<string, mixed>
     */
    public function translatedAll(?string $locale = null, ?string $fallbackLocale = null): array
    {
        return collect($this->translatableFields())
            ->mapWithKeys(fn (string $field) => [
                $field => $this->translated($field, $locale, $fallbackLocale),
            ])
            ->all();
    }

    /* ------------------------------------------------------------------
    |  Write helpers — used by TranslationOrchestrator
    | ------------------------------------------------------------------ */

    /**
     * Upsert all locale rows in a single DB query — no N+1.
     *
     * @param  array<string, array<string, string>>  $byLocale  ['ar' => ['name'=>'...'], ...]
     */
    public function upsertTranslations(array $byLocale): void
    {
        if (empty($byLocale)) {
            return;
        }

        $fk = $this->inferTranslationForeignKey();
        $now = now();

        /** @var class-string<Model> $translationClass */
        $translationClass = $this->translationModelClass();

        $rows = [];
        $fieldsToUpdate = [];
        foreach ($byLocale as $locale => $fields) {
            $allowedFields = array_intersect_key(
                $fields,
                array_flip($this->translatableFields())
            );

            if ($allowedFields === []) {
                continue;
            }

            $fieldsToUpdate = array_merge($fieldsToUpdate, array_keys($allowedFields));
            $rows[] = array_merge($allowedFields, [
                $fk => $this->getKey(),
                'locale' => $locale,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($rows === []) {
            return;
        }

        $fieldsToUpdate = array_values(array_unique($fieldsToUpdate));

        $translationClass::upsert(
            $rows,
            uniqueBy: [$fk, 'locale'],
            update: array_merge($fieldsToUpdate, ['updated_at'])
        );
    }

    /**
     * Convenience: trigger the full translation pipeline.
     * Prefer calling $model->autoTranslate() from the model itself.
     *
     * @param  array<string, string>  $sourceData
     */
    public function dispatchTranslations(array $sourceData, ?string $sourceLocale = null): void
    {
        app(TranslationOrchestrator::class)->handle($this, $sourceData, $sourceLocale);
    }

    /* ------------------------------------------------------------------
    |  Internal
    | ------------------------------------------------------------------ */

    private function inferTranslationForeignKey(): string
    {
        if (property_exists($this, 'translationForeignKey')) {
            return $this->translationForeignKey; // @phpstan-ignore-line
        }

        return Str::snake(class_basename($this)).'_id';
    }
}
