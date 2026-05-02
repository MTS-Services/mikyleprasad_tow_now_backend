<?php

namespace App\Contracts\Currency;

use App\Models\Currency;

interface CurrencyContextInterface
{
    public function displayCurrency(): Currency;

    public function code(): string;
}
