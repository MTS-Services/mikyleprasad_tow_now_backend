<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $codes = config('currency.enabled_codes', ['USD']);

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where(function ($query) use ($codes): void {
                    $query->where('is_active', true)->whereIn('code', $codes);
                }),
            ],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'published', 'archived'])],
        ];
    }
}
