<?php

namespace App\Services\Currency;

use App\Models\Currency;
use Illuminate\Http\Request;

/**
 * Resolves which currency_id to persist for new/updated prices (plans, products, etc.):
 * valid X-App-Currency header → optional body currency_code → user's preferred_currency_id → base currency.
 */
final class PersistedListingCurrencyResolver
{
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * @param  string|null  $bodyCurrencyCode  Validated ISO code from request body, if any.
     */
    public function resolve(?string $bodyCurrencyCode = null): int
    {
        $enabled = $this->enabledCodesUpper();

        if (($currency = $this->fromHeader($enabled)) !== null) {
            return $currency->id;
        }

        if ($bodyCurrencyCode !== null && $bodyCurrencyCode !== '') {
            $code = strtoupper(trim($bodyCurrencyCode));
            if (in_array($code, $enabled, true)) {
                $currency = Currency::query()->where('code', $code)->active()->first();
                if ($currency !== null) {
                    return $currency->id;
                }
            }
        }

        if (($currency = $this->fromUserPreference($enabled)) !== null) {
            return $currency->id;
        }

        return Currency::base()->id;
    }

    /**
     * @param  list<string>  $enabled
     */
    private function fromHeader(array $enabled): ?Currency
    {
        if (! config('currency.currency_override_enabled', true)) {
            return null;
        }

        $headerName = (string) config('currency.currency_override_header', 'X-App-Currency');
        $raw = $this->request->header($headerName);

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $code = strtoupper(trim($raw));

        if (! in_array($code, $enabled, true)) {
            return null;
        }

        return Currency::query()->where('code', $code)->active()->first();
    }

    /**
     * @param  list<string>  $enabled
     */
    private function fromUserPreference(array $enabled): ?Currency
    {
        $user = $this->request->user();
        if ($user === null || $user->preferred_currency_id === null) {
            return null;
        }

        $currency = Currency::query()->whereKey($user->preferred_currency_id)->active()->first();
        if ($currency === null) {
            return null;
        }

        return in_array(strtoupper($currency->code), $enabled, true) ? $currency : null;
    }

    /**
     * @return list<string>
     */
    private function enabledCodesUpper(): array
    {
        return array_values(array_map('strtoupper', config('currency.enabled_codes', [])));
    }
}
