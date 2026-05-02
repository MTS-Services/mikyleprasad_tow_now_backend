<?php

namespace App\Enums;

enum CurrencyRateSource: string
{
    case Manual = 'manual';
    case Api = 'api';
}
