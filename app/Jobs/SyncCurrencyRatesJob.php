<?php

namespace App\Jobs;

use App\Models\Currency;
use App\Services\Currency\CurrencyRateLedger;
use App\Services\Currency\ExchangeRateService;
use App\Services\Currency\FrankfurterExchangeRateClient;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncCurrencyRatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public int $timeout = 120;

    public function handle(
        FrankfurterExchangeRateClient $client,
        CurrencyRateLedger $ledger,
        ExchangeRateService $exchangeRateService,
    ): void {
        if (! config('currency.fx_sync.enabled', false)) {
            Log::info('currency.fx.sync_skipped_disabled');

            return;
        }

        $base = Currency::base();
        $enabledCodes = array_map('strtoupper', config('currency.enabled_codes', []));

        $quoteCurrencies = Currency::query()
            ->active()
            ->whereIn('code', $enabledCodes)
            ->whereKeyNot($base->id)
            ->orderBy('code')
            ->get();

        if ($quoteCurrencies->isEmpty()) {
            Log::info('currency.fx.sync_skipped_no_quotes', ['base' => $base->code]);

            return;
        }

        $toCodes = $quoteCurrencies->pluck('code')->map(fn (string $c): string => strtoupper($c))->all();

        $payload = $client->fetchLatest($base->code, $toCodes);
        $batchId = (string) Str::uuid();

        $effectiveAt = Carbon::parse($payload['date'])->utc()->startOfDay();

        $rateMin = (float) config('currency.rate_min', 1e-10);
        $rateMax = (float) config('currency.rate_max', 1e10);

        foreach ($quoteCurrencies as $quote) {
            $code = strtoupper($quote->code);
            $rawRate = $payload['rates'][$code] ?? $payload['rates'][strtolower($code)] ?? null;

            if ($rawRate === null) {
                Log::warning('currency.fx.missing_rate_for_code', ['code' => $code]);

                continue;
            }

            $rateStr = number_format((float) $rawRate, 10, '.', '');

            if ((float) $rateStr < $rateMin || (float) $rateStr > $rateMax) {
                Log::warning('currency.fx.rate_out_of_bounds', [
                    'code' => $code,
                    'rate' => $rateStr,
                    'min' => $rateMin,
                    'max' => $rateMax,
                ]);

                continue;
            }

            $ledger->recordApi(
                (int) $base->id,
                (int) $quote->id,
                $rateStr,
                $effectiveAt,
                $batchId,
            );
        }

        $exchangeRateService->flushCacheForAllConfiguredPairs();

        Log::info('currency.fx.sync_completed', [
            'batch_id' => $batchId,
            'base' => $base->code,
            'effective_at' => $effectiveAt->toIso8601String(),
        ]);
    }
}
