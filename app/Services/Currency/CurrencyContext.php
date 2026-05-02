<?php

namespace App\Services\Currency;

use App\Contracts\Currency\CurrencyContextInterface;
use App\Models\Currency;

final class CurrencyContext implements CurrencyContextInterface
{
    private ?Currency $displayCurrency = null;

    public function __construct(
        private readonly CurrencyDisplayResolver $resolver,
    ) {}

    public function displayCurrency(): Currency
    {
        return $this->displayCurrency ??= $this->resolver->resolve();
    }

    public function code(): string
    {
        return $this->displayCurrency()->code;
    }
}
