<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'preferred_currency' => $this->whenLoaded(
                'preferredCurrency',
                fn () => new PublicCurrencyResource($this->preferredCurrency)
            ),
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'vehicle' => $this->whenLoaded('vehicle', fn (): array => [
                'id' => $this->vehicle->id,
                'car_brand' => $this->vehicle->car_brand,
                'car_model' => $this->vehicle->car_model,
                'car_type' => $this->vehicle->car_type,
                'license_plate' => $this->vehicle->license_plate,
                'location' => $this->vehicle->location,
                'truck_image_url' => storage_url($this->vehicle->truck_image_path),
                'driving_license_image_url' => storage_url($this->vehicle->driving_license_image_path),
                'car_legal_documents_url' => storage_url($this->vehicle->car_legal_documents_path),
            ]),
            'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
