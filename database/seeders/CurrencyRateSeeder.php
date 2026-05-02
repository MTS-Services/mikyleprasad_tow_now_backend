<?php

namespace Database\Seeders;

use App\Enums\CurrencyRateSource;
use App\Models\Currency;
use App\Models\CurrencyExchangeRate;
use App\Services\Currency\ExchangeRateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class CurrencyRateSeeder extends Seeder
{
    /**
     * Identifies rows owned by this seeder so re-runs can replace them without touching API/manual rates.
     */
    private const SEED_SYNC_BATCH_ID = 'f1000000-0000-4000-8000-0000000000d1';

    /**
     * Quote currency units per 1 unit of base (same convention as {@see ExchangeRateService} / Frankfurter).
     */
    private const QUOTE_PER_BASE_BY_CODE = [
        'EUR' => '0.9200000000',
        'GBP' => '0.7900000000',
        'SAR' => '3.7500000000',
        'AED' => '3.6700000000',
        'JPY' => '150.0000000000',
        'CAD' => '1.3600000000',
        'AUD' => '1.5300000000',
        'CHF' => '0.8800000000',
        'CNY' => '7.2000000000',
        'INR' => '83.0000000000',
        'BRL' => '5.0000000000',
        'MXN' => '17.0000000000',
        'ZAR' => '18.5000000000',
        'TRY' => '32.0000000000',
        'SEK' => '10.5000000000',
        'NOK' => '10.8000000000',
        'DKK' => '6.9000000000',
        'PLN' => '4.0000000000',
        'NZD' => '1.6500000000',
        'SGD' => '1.3400000000',
        'HKD' => '7.8000000000',
        'KRW' => '1350.0000000000',
        'THB' => '35.0000000000',
        'EGP' => '48.0000000000',
        'ILS' => '3.6500000000',
    ];

    public function run(): void
    {
        $base = Currency::base();

        CurrencyExchangeRate::query()
            ->where('sync_batch_id', self::SEED_SYNC_BATCH_ID)
            ->delete();

        $effectiveAt = now()->subMinute();

        foreach (Currency::query()->orderBy('id')->get() as $quote) {
            if ($quote->id === $base->id) {
                continue;
            }

            $code = strtoupper($quote->code);
            $rate = self::QUOTE_PER_BASE_BY_CODE[$code] ?? null;

            if ($rate === null) {
                $rate = '1.0000000000';
                Log::warning('currency_rate_seeder.unknown_code_using_parity', [
                    'code' => $code,
                    'quote_currency_id' => $quote->id,
                    'hint' => 'Add a realistic QUOTE_PER_BASE_BY_CODE entry for this currency.',
                ]);
            }

            $row = new CurrencyExchangeRate;
            $row->base_currency_id = $base->id;
            $row->quote_currency_id = $quote->id;
            $row->rate = $rate;
            $row->effective_at = $effectiveAt;
            $row->source = CurrencyRateSource::Manual;
            $row->sync_batch_id = self::SEED_SYNC_BATCH_ID;
            $row->created_by_user_id = null;
            $row->created_at = now();
            $row->save();
        }

        app(ExchangeRateService::class)->flushCacheForAllConfiguredPairs();
    }
}
