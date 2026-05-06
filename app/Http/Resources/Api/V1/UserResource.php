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
                'name' => $this->vehicle->name,
                'description' => $this->vehicle->description,
                'brand' => $this->vehicle->brand,
                'model' => $this->vehicle->model,
                'capacity' => $this->vehicle->capacity,
                'license_plate' => $this->vehicle->license_plate,
                'truck_image_url' => storage_url($this->vehicle->truck_image),
                'driving_license_image_url' => storage_url($this->vehicle->driving_license_image),
                'legal_documents_url' => storage_url($this->vehicle->legal_documents),
                'insurance_status' => $this->vehicle->insurance_status,
            ]),
            'address' => $this->address,
            'bio' => $this->bio,
            'is_suspended' => $this->is_suspended,
            'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
