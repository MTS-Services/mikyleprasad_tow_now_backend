<?php

namespace App\Support\Currency;

use App\Contracts\Currency\CurrencyContextInterface;
use App\Models\Currency;
use App\Services\Currency\ExchangeRateService;

final class MoneyPresenter
{
    public static function formatAmount(float|string|null $amount, Currency $currency): string
    {
        $places = (int) $currency->decimal_places;

        return number_format((float) $amount, $places, '.', '');
    }

    /**
     * @return array{amount: string, currency: string}
     */
    public static function listing(float|string|null $amount, Currency $currency): array
    {
        return [
            'amount' => self::formatAmount($amount ?? 0, $currency),
            'currency' => $currency->code,
        ];
    }

    /**
     * @return array{
     *     price: array{amount: string, currency: string},
     *     price_display: array{amount: string, currency: string}|null,
     *     conversion_available: bool
     * }
     */
    public static function priceWithDisplay(
        float|string|null $amount,
        Currency $listingCurrency,
    ): array {
        $price = self::listing($amount, $listingCurrency);

        $displayCurrency = app(CurrencyContextInterface::class)->displayCurrency();
        $exchange = app(ExchangeRateService::class);

        if ($listingCurrency->id === $displayCurrency->id) {
            return [
                'price' => $price,
                'price_display' => null,
                'conversion_available' => true,
            ];
        }

        $converted = $exchange->convert((string) ($amount ?? 0), $listingCurrency, $displayCurrency);

        if ($converted === null) {
            return [
                'price' => $price,
                'price_display' => null,
                'conversion_available' => false,
            ];
        }

        return [
            'price' => $price,
            'price_display' => self::listing($converted, $displayCurrency),
            'conversion_available' => true,
        ];
    }
}
