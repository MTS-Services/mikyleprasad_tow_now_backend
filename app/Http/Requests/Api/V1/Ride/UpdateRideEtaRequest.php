<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ride;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRideEtaRequest extends FormRequest
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
            'eta_minutes' => ['required', 'integer', 'min:1', 'max:720'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
