<?php

namespace App\Services\Currency;

use App\Enums\CurrencyRateSource;
use App\Models\Currency;
use App\Models\CurrencyExchangeRate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class ExchangeRateService
{
    public function latestRate(Currency $base, Currency $quote): ?CurrencyExchangeRate
    {
        if ($base->id === $quote->id) {
            return null;
        }

        $ttl = max(1, (int) config('currency.cache_ttl', 120));
        $cacheKey = $this->cacheKey($base->id, $quote->id, 'latest_id');

        // Cache the row id only — serializing Eloquent models can yield __PHP_Incomplete_Class on read.
        $id = Cache::remember($cacheKey, $ttl, function () use ($base, $quote): ?int {
            $row = $this->queryRateAt($base, $quote, now());

            return $row?->id;
        });

        if ($id === null) {
            return null;
        }

        return CurrencyExchangeRate::query()->find($id);
    }

    public function rateAt(Currency $base, Currency $quote, CarbonInterface $moment): ?CurrencyExchangeRate
    {
        if ($base->id === $quote->id) {
            return null;
        }

        return $this->queryRateAt($base, $quote, $moment);
    }

    public function forgetCacheForPair(int $baseCurrencyId, int $quoteCurrencyId): void
    {
        Cache::forget($this->cacheKey($baseCurrencyId, $quoteCurrencyId, 'latest_id'));
    }

    /**
     * Convert amount between two currencies using stored base pivot rates (BCMath).
     */
    public function convert(string $amount, Currency $from, Currency $to): ?string
    {
        if ($from->id === $to->id) {
            return $this->roundToCurrency($amount, $to);
        }

        $base = Currency::base();

        $inBase = $this->toBaseAmount($amount, $from, $base);
        if ($inBase === null) {
            return null;
        }

        $out = $this->fromBaseAmount($inBase, $to, $base);
        if ($out === null) {
            return null;
        }

        return $this->roundToCurrency($out, $to);
    }

    public function flushCacheForAllConfiguredPairs(): void
    {
        $base = Currency::base();
        foreach (config('currency.enabled_codes', []) as $code) {
            $quote = Currency::query()->where('code', strtoupper($code))->first();
            if ($quote === null || $quote->id === $base->id) {
                continue;
            }
            $this->forgetCacheForPair($base->id, $quote->id);
        }
    }

    private function queryRateAt(Currency $base, Currency $quote, CarbonInterface $moment): ?CurrencyExchangeRate
    {
        return CurrencyExchangeRate::query()
            ->where('base_currency_id', $base->id)
            ->where('quote_currency_id', $quote->id)
            ->where('effective_at', '<=', $moment)
            ->orderByDesc('effective_at')
            ->orderByRaw("CASE WHEN source = '".CurrencyRateSource::Manual->value."' THEN 1 ELSE 0 END DESC")
            ->orderByDesc('id')
            ->first();
    }

    private function cacheKey(int $baseId, int $quoteId, string $suffix): string
    {
        return "currency.rate.{$baseId}.{$quoteId}.{$suffix}";
    }

    private function toBaseAmount(string $amount, Currency $from, Currency $base): ?string
    {
        if ($from->id === $base->id) {
            return $amount;
        }

        $row = $this->latestRate($base, $from);
        if ($row === null) {
            return null;
        }

        $rate = (string) $row->rate;

        return bcdiv($amount, $rate, 12);
    }

    private function fromBaseAmount(string $baseAmount, Currency $to, Currency $base): ?string
    {
        if ($to->id === $base->id) {
            return $baseAmount;
        }

        $row = $this->latestRate($base, $to);
        if ($row === null) {
            return null;
        }

        $rate = (string) $row->rate;

        return bcmul($baseAmount, $rate, 12);
    }

    private function roundToCurrency(string $amount, Currency $currency): string
    {
        $places = (int) $currency->decimal_places;

        return number_format(round((float) $amount, $places, PHP_ROUND_HALF_UP), $places, '.', '');
    }
}
