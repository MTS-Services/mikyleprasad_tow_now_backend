<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'symbol', 'decimal_places', 'is_active', 'sort_order'])]
class Currency extends Model
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEnabledInConfig(Builder $query): Builder
    {
        $codes = config('currency.enabled_codes', []);

        return $query->whereIn('code', $codes);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', strtoupper($code))->first();
    }

    public static function base(): self
    {
        $code = strtoupper((string) config('currency.base_currency', 'USD'));

        return static::query()->where('code', $code)->firstOrFail();
    }

    public function ratesAsBase(): HasMany
    {
        return $this->hasMany(CurrencyExchangeRate::class, 'base_currency_id');
    }

    public function ratesAsQuote(): HasMany
    {
        return $this->hasMany(CurrencyExchangeRate::class, 'quote_currency_id');
    }
    
}
