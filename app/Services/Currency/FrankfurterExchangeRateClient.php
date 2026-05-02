<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FrankfurterExchangeRateClient
{
    /**
     * @param  list<string>  $toCodes  ISO 4217 uppercase
     * @return array{date: string, rates: array<string, float|int>}
     */
    public function fetchLatest(string $from, array $toCodes): array
    {
        $allowed = config('currency.fx_sync.allowed_hosts', ['api.frankfurter.app']);
        $host = $allowed[0] ?? 'api.frankfurter.app';

        $to = implode(',', array_map('strtoupper', $toCodes));
        $from = strtoupper($from);

        $url = "https://{$host}/latest";
        $timeout = max(1, (int) config('currency.fx_sync.timeout_seconds', 10));

        $response = Http::withOptions([
            'allow_redirects' => false,
        ])
            ->timeout($timeout)
            ->acceptJson()
            ->get($url, [
                'from' => $from,
                'to' => $to,
            ]);

        if ($response->failed()) {
            Log::warning('currency.fx.fetch_failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        /** @var array{date?: string, rates?: array<string, float|int>} $json */
        $json = $response->json();

        if (! isset($json['date'], $json['rates']) || ! is_array($json['rates'])) {
            throw new \RuntimeException('Invalid Frankfurter response payload.');
        }

        return [
            'date' => (string) $json['date'],
            'rates' => $json['rates'],
        ];
    }
}
