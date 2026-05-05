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
            'driver_profile' => $this->whenLoaded('driverProfile', fn (): array => [
                'id' => $this->driverProfile->id,
                'car_brand' => $this->driverProfile->car_brand,
                'car_model' => $this->driverProfile->car_model,
                'car_type' => $this->driverProfile->car_type,
                'license_plate' => $this->driverProfile->license_plate,
                'location' => $this->driverProfile->location,
                'truck_image_url' => storage_url($this->driverProfile->truck_image_path),
                'driving_license_image_url' => storage_url($this->driverProfile->driving_license_image_path),
                'car_legal_documents_url' => storage_url($this->driverProfile->car_legal_documents_path),
            ]),
            'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
