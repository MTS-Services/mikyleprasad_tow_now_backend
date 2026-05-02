<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

/**
 * Resolves localized attribute values from a related translations collection and parent fallback columns.
 *
 * Use for models that follow the pattern: parent row with canonical fields + hasMany translation rows
 * keyed by a locale column (e.g. product + product_translations).
 */
final class LocaleTranslationResolver
{
    /**
     * Choose the translation row for the requested locale, then the application fallback locale.
     */
    public function matchingTranslation(
        Collection $translationRows,
        string $localeColumn,
        ?string $locale = null,
        ?string $fallbackLocale = null
    ): ?Model {
        $locale ??= App::getLocale();
        $fallbackLocale ??= (string) config('app.fallback_locale', 'en');

        $match = $translationRows->firstWhere($localeColumn, $locale);

        if ($match !== null) {
            return $match;
        }

        if ($locale !== $fallbackLocale) {
            return $translationRows->firstWhere($localeColumn, $fallbackLocale);
        }

        return null;
    }

    /**
     * Merge translation attributes with parent fallbacks.
     *
     * @param  array<string, string>  $fieldMap  Translation attribute name => parent column name for fallback
     * @param  array<int, string>  $nonEmptyTranslationKeys  Translation keys where empty string should use parent value
     * @return array<string, mixed>
     */
    public function localizedFields(
        Model $parent,
        ?Model $translationRow,
        array $fieldMap,
        array $nonEmptyTranslationKeys = []
    ): array {
        $out = [];

        foreach ($fieldMap as $translationKey => $parentColumn) {
            $requireNonEmpty = in_array($translationKey, $nonEmptyTranslationKeys, true);

            if ($translationRow === null) {
                $out[$translationKey] = $parent->getAttribute($parentColumn);

                continue;
            }

            $translated = $translationRow->getAttribute($translationKey);

            if ($requireNonEmpty) {
                $out[$translationKey] = ($translated !== null && $translated !== '')
                    ? $translated
                    : $parent->getAttribute($parentColumn);
            } else {
                $out[$translationKey] = $translated !== null
                    ? $translated
                    : $parent->getAttribute($parentColumn);
            }
        }

        return $out;
    }

    /**
     * Load a translations relation if needed, pick a row, and merge fields.
     *
     * @param  array<string, string>  $fieldMap
     * @param  array<int, string>  $nonEmptyTranslationKeys
     * @return array<string, mixed>
     */
    public function fromRelation(
        Model $parent,
        string $relation,
        string $localeColumn,
        array $fieldMap,
        array $nonEmptyTranslationKeys = [],
        ?string $locale = null,
        ?string $fallbackLocale = null
    ): array {
        $rows = $parent->relationLoaded($relation)
            ? $parent->getRelation($relation)
            : $parent->{$relation}()->get();

        $row = $this->matchingTranslation($rows, $localeColumn, $locale, $fallbackLocale);

        return $this->localizedFields($parent, $row, $fieldMap, $nonEmptyTranslationKeys);
    }
}
