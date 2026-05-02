<?php

namespace App\Rules;

use App\Models\Currency;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EnabledCurrencyCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail(__('validation.string', ['attribute' => $attribute]));

            return;
        }

        $code = strtoupper(trim($value));
        $enabled = array_map('strtoupper', config('currency.enabled_codes', []));

        if (! in_array($code, $enabled, true)) {
            $fail(__('validation.in', ['attribute' => $attribute]));

            return;
        }

        $exists = Currency::query()->where('code', $code)->active()->exists();
        if (! $exists) {
            $fail(__('validation.exists', ['attribute' => $attribute]));
        }
    }
}
