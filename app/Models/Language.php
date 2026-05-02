<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'locale',
    'name',
    'native_name',
    'country_code',
    'is_rtl',
    'is_active',
    'is_default',
    'sort_order',
])]
class Language extends Model
{
    protected $table = 'languages';

    protected function casts(): array
    {
        return [
            'is_rtl' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /* --------------------------------------------------------------
    |                          Scopes
    | -------------------------------------------------------------- */

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /* --------------------------------------------------------------
    |                     Static Cache Helpers
    |
    | Results are cached so the DB is not queried on every request.
    | Call Language::clearCache() after any write to this table.
    | -------------------------------------------------------------- */

    /**
     * All active languages, ordered by sort_order.
     * Served from cache after the first call.
     *
     * @return Collection<int, Language>
     */
    public static function allActive(): Collection
    {
        $store = Cache::store(config('translation.cache.store'));

        $cached = $store->get('languages:active');
        if ($cached instanceof Collection) {
            return $cached;
        }

        // Guard against corrupted/old serialized cache values.
        $store->forget('languages:active');

        $fresh = static::active()->orderBy('sort_order')->get();
        $store->put('languages:active', $fresh, config('translation.cache.ttl'));

        return $fresh;
    }

    /**
     * Active languages excluding the source locale — these are the
     * translation *targets* for every auto-translate dispatch.
     *
     * @return Collection<int, Language>
     */
    public static function translationTargets(?string $excludeLocale = null): Collection
    {
        $exclude = $excludeLocale ?? config('translation.source_locale', 'en');

        return static::allActive()
            ->filter(fn (Language $lang) => $lang->locale !== $exclude)
            ->values();
    }

    /**
     * The single default / source language row.
     */
    public static function defaultLanguage(): ?static
    {
        $store = Cache::store(config('translation.cache.store'));

        $cached = $store->get('languages:default');
        if ($cached instanceof static || $cached === null) {
            return $cached;
        }

        // Guard against corrupted/old serialized cache values.
        $store->forget('languages:default');

        $fresh = static::where('is_default', true)->first();
        $store->put('languages:default', $fresh, config('translation.cache.ttl'));

        return $fresh;
    }

    /**
     * Flush language cache entries.
     * Call this in an observer or after seeding/admin updates.
     */
    public static function clearCache(): void
    {
        $store = Cache::store(config('translation.cache.store'));
        $store->forget('languages:active');
        $store->forget('languages:default');
    }
}
