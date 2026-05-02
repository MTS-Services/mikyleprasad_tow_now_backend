<?php

namespace App\Services\Currency;

use App\Models\Currency;
use App\Support\Http\RequestPreferenceResolution;
use Illuminate\Http\Request;

class CurrencyDisplayResolver
{
    public function __construct(private readonly Request $request) {}

    public function resolve(): Currency
    {
        $enabled = $this->enabledCodes();
        $headerBeforeUser = RequestPreferenceResolution::headerBeforeUserPreferences($this->request);

        if ($headerBeforeUser) {
            $fromXHeader = $this->currencyFromOverrideHeader($enabled);
            if ($fromXHeader !== null) {
                return $fromXHeader;
            }

            $fromAcceptCurrency = $this->currencyFromAcceptCurrencyHeader($enabled);
            if ($fromAcceptCurrency !== null) {
                return $fromAcceptCurrency;
            }

            $fromUser = $this->currencyFromUserPreference($enabled);
            if ($fromUser !== null) {
                return $fromUser;
            }
        } else {
            $fromUser = $this->currencyFromUserPreference($enabled);
            if ($fromUser !== null) {
                return $fromUser;
            }

            $fromXHeader = $this->currencyFromOverrideHeader($enabled);
            if ($fromXHeader !== null) {
                return $fromXHeader;
            }

            $fromAcceptCurrency = $this->currencyFromAcceptCurrencyHeader($enabled);
            if ($fromAcceptCurrency !== null) {
                return $fromAcceptCurrency;
            }
        }

        return Currency::base();
    }

    /**
     * @param  list<string>  $enabled
     */
    private function currencyFromOverrideHeader(array $enabled): ?Currency
    {
        if (! config('currency.currency_override_enabled', true)) {
            return null;
        }

        $headerName = (string) config('currency.currency_override_header', 'X-App-Currency');
        $header = $this->request->header($headerName);
        if (! is_string($header) || $header === '') {
            return null;
        }

        $code = strtoupper(trim($header));
        if (! in_array($code, $enabled, true)) {
            return null;
        }

        return $this->findActiveCurrency($code);
    }

    /**
     * Accept-Currency: a comma-separated list like "EUR,USD;q=0.8".
     *
     * @param  list<string>  $enabled
     */
    private function currencyFromAcceptCurrencyHeader(array $enabled): ?Currency
    {
        $raw = $this->request->header('Accept-Currency');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        foreach (explode(',', $raw) as $part) {
            $token = trim(explode(';', $part)[0]);
            if ($token === '') {
                continue;
            }

            $code = strtoupper($token);
            if (! in_array($code, $enabled, true)) {
                continue;
            }

            $currency = $this->findActiveCurrency($code);
            if ($currency !== null) {
                return $currency;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $enabled
     */
    private function currencyFromUserPreference(array $enabled): ?Currency
    {
        $user = $this->request->user();
        if ($user === null || $user->preferred_currency_id === null) {
            return null;
        }

        $currency = Currency::query()
            ->whereKey($user->preferred_currency_id)
            ->active()
            ->first();

        if ($currency === null || ! in_array(strtoupper($currency->code), $enabled, true)) {
            return null;
        }

        return $currency;
    }

    /**
     * @return list<string>
     */
    private function enabledCodes(): array
    {
        return array_values(array_map('strtoupper', config('currency.enabled_codes', [])));
    }

    private function findActiveCurrency(string $code): ?Currency
    {
        return Currency::query()
            ->where('code', $code)
            ->active()
            ->first();
    }
}
