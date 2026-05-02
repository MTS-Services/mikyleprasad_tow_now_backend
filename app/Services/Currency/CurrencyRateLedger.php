<?php

namespace App\Services\Currency;

use App\Enums\CurrencyRateSource;
use App\Models\CurrencyExchangeRate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class CurrencyRateLedger
{
    public function recordManual(
        int $baseCurrencyId,
        int $quoteCurrencyId,
        string $rate,
        CarbonInterface $effectiveAt,
        int $createdByUserId,
    ): CurrencyExchangeRate {
        $row = new CurrencyExchangeRate;
        $row->base_currency_id = $baseCurrencyId;
        $row->quote_currency_id = $quoteCurrencyId;
        $row->rate = $rate;
        $row->effective_at = $effectiveAt;
        $row->source = CurrencyRateSource::Manual;
        $row->sync_batch_id = null;
        $row->created_by_user_id = $createdByUserId;
        $row->save();

        Log::info('currency.rate.manual_created', [
            'base_currency_id' => $baseCurrencyId,
            'quote_currency_id' => $quoteCurrencyId,
            'rate' => $rate,
            'effective_at' => $effectiveAt->toIso8601String(),
            'user_id' => $createdByUserId,
        ]);

        return $row;
    }

    public function recordApi(
        int $baseCurrencyId,
        int $quoteCurrencyId,
        string $rate,
        CarbonInterface $effectiveAt,
        string $syncBatchId,
    ): ?CurrencyExchangeRate {
        if ($this->shouldSkipDuplicateApiRow($baseCurrencyId, $quoteCurrencyId, $rate, $effectiveAt)) {
            return null;
        }

        $row = new CurrencyExchangeRate;
        $row->base_currency_id = $baseCurrencyId;
        $row->quote_currency_id = $quoteCurrencyId;
        $row->rate = $rate;
        $row->effective_at = $effectiveAt;
        $row->source = CurrencyRateSource::Api;
        $row->sync_batch_id = $syncBatchId;
        $row->created_by_user_id = null;
        $row->save();

        return $row;
    }

    private function shouldSkipDuplicateApiRow(
        int $baseCurrencyId,
        int $quoteCurrencyId,
        string $rate,
        CarbonInterface $effectiveAt,
    ): bool {
        $latest = CurrencyExchangeRate::query()
            ->where('base_currency_id', $baseCurrencyId)
            ->where('quote_currency_id', $quoteCurrencyId)
            ->where('source', CurrencyRateSource::Api)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if ($latest === null) {
            return false;
        }

        return $latest->effective_at->equalTo($effectiveAt)
            && bccomp((string) $latest->rate, $rate, 10) === 0;
    }
}
