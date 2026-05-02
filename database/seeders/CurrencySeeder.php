<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $codes = config('currency.enabled_codes', ['USD', 'EUR', 'SAR']);

        $definitions = [
            'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'sort_order' => 0],
            'EUR' => ['name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'sort_order' => 1],
            'SAR' => ['name' => 'Saudi Riyal', 'symbol' => 'SR', 'decimal_places' => 2, 'sort_order' => 2],
        ];

        foreach ($codes as $code) {
            $code = strtoupper($code);
            if (! isset($definitions[$code])) {
                continue;
            }

            Currency::query()->updateOrCreate(
                ['code' => $code],
                [
                    ...$definitions[$code],
                    'is_active' => true,
                ]
            );
        }
    }
}
