<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FindDriversRequest extends FormRequest
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
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['Online', 'Offline', 'online', 'offline'])],
            'featured' => ['sometimes', 'nullable', 'string', Rule::in(['1', '0', 'true', 'false'])],
            'tab' => ['sometimes', 'nullable', 'string', Rule::in(['all', 'featured_drivers'])],
            'lite' => ['sometimes', 'nullable', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
