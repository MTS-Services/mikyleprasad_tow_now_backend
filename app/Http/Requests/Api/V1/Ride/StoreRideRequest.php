<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ride;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRideRequest extends FormRequest
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
            'driver_id' => ['required', 'integer', 'exists:users,id'],
            'pickup_location' => ['required', 'string', 'max:500'],
            'dropoff_location' => ['required', 'string', 'max:500'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'pickup_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'dropoff_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'dropoff_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'offline_temp_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'synced_from_offline' => ['sometimes', 'boolean'],
            'problem_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'problem_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'estimated_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'payment_status' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
