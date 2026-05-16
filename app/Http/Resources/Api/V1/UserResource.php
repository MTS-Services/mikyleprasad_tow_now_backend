<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\RideStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection as SupportCollection;
use Stripe\Collection;

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
            'avatar_url' => $this->avatar ? storage_url($this->avatar) : null,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'preferred_currency' => $this->whenLoaded(
                'preferredCurrency',
                fn() => new PublicCurrencyResource($this->preferredCurrency)
            ),
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'vehicle' => $this->whenLoaded('vehicle', fn(): array => [
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
            'is_featured' => $this->is_featured,
            'approval_status' => $this->approval_status?->value ?? $this->approval_status,
            'status' => $this->status,
            'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'ride_statistics' => $this->when(
                $this->role?->value === 'driver',
                fn() => [
                    'total_rides' => $this->total_rides,
                    'completed_rides' => $this->completed_rides,
                    'cancelled_rides' => $this->cancelled_rides,
                    'active_rides' => $this->active_rides,
                ]
            ),

            'review_stats' => $this->when(
                $this->role?->value === 'driver',
                fn() => [
                    'total_reviews'  => $this->driver_reviews_count ?? 0,
                    'average_rating' => $this->driver_reviews_avg_rating
                        ? round((float) $this->driver_reviews_avg_rating, 1)
                        : null,
                ]
            ),
            'ride_statistics_customer' => $this->when(
                $this->role?->value === 'user',
                fn() => [
                    'total_rides' => $this->requestedRides->count(),

                    'completed_rides' => $this->requestedRides
                        ->where('status', RideStatusEnum::COMPLETED_USER)
                        ->count(),

                    'cancelled_rides' => $this->requestedRides
                        ->whereIn('status', [
                            RideStatusEnum::CANCELLED_BY_USER,
                            RideStatusEnum::CANCELLED_BY_DRIVER,
                            RideStatusEnum::SYSTEM_CANCELLED,
                            RideStatusEnum::EXPIRED,
                        ])
                        ->count(),

                    'active_rides' => $this->requestedRides
                        ->whereIn('status', [
                            RideStatusEnum::PENDING,
                            RideStatusEnum::ACTIVE,
                            RideStatusEnum::ARRIVED,
                        ])
                        ->count(),
                ]
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'requested_rides' => $this->when(
                $this->role?->value === 'user',
                fn() => $this->whenLoaded(
                    'requestedRides',
                    fn() => $this->requestedRides->map(fn($ride) => [
                        'id' => $ride->id,
                        'pickup_location' => $ride->pickup_location,
                        'dropoff_location' => $ride->dropoff_location,
                        'status' => $ride->status,

                        'driver' => $ride->driver ? [
                            'id' => $ride->driver->id,
                            'name' => $ride->driver->name,
                            'avatar_url' => $ride->driver->avatar
                                ? storage_url($ride->driver->avatar)
                                : null,
                        ] : null,

                        'review' => $ride->review ? [
                            'id' => $ride->review->id,
                            'rating' => $ride->review->rating,
                            'body' => $ride->review->body,
                        ] : null,

                        'created_at' => $ride->created_at?->toIso8601String(),
                    ])
                )
            ),
        ];
    }
}
