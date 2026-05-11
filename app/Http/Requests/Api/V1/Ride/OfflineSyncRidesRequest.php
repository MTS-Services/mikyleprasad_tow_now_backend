<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ride;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OfflineSyncRidesRequest extends FormRequest
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
            'rides' => ['required', 'array', 'min:1'],
            'rides.*.driver_id' => ['required', 'integer', 'exists:users,id'],
            'rides.*.pickup_location' => ['required', 'string', 'max:500'],
            'rides.*.dropoff_location' => ['required', 'string', 'max:500'],
            'rides.*.notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'rides.*.pickup_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'rides.*.pickup_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'rides.*.dropoff_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'rides.*.dropoff_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'rides.*.offline_temp_id' => ['required', 'string', 'max:64'],
            'rides.*.problem_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'rides.*.problem_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'rides.*.estimated_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'rides.*.payment_status' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
