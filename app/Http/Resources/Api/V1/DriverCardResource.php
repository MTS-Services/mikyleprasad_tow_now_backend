<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\AccountStatus;
use App\Enums\RideStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lite = filter_var(
            (string) ($request->header('X-Low-Bandwidth', $request->query('lite', '0'))),
            FILTER_VALIDATE_BOOLEAN
        );

        $status = ($this->status?->value ?? $this->status) === AccountStatus::ACTIVE->value && ! $this->is_suspended
            ? 'Online'
            : 'Offline';

        $vehicleName = $this->vehicle?->name
            ?: trim(sprintf('%s %s', (string) ($this->vehicle?->brand ?? ''), (string) ($this->vehicle?->model ?? '')));


        $reviews = $this->relationLoaded('assignedRides')
            ? $this->assignedRides->pluck('review')->filter()->values()
            : collect();

        $base = [
            'id' => $this->id,
            'initials' => $this->resolveInitials(),
            'name' => $this->name,
            'rating'      => round((float) $reviews->avg('rating'), 1),
            'reviews'     => $reviews->count(),
            'location' => $this->address ?: 'Location unavailable',
            'status' => $status,
            'phoneNumber' => $this->phone,
            'avatar_url' => $this->avatar ? storage_url($this->avatar) : null,


            'totalRides'     => $this->relationLoaded('assignedRides')
                ? $this->assignedRides->count()
                : 0,

            'completedRides' => $this->relationLoaded('assignedRides')
                ? $this->assignedRides->where('status', RideStatusEnum::COMPLETED_USER)->count()
                : 0,

            'canceledRides'  => $this->relationLoaded('assignedRides')
                ? $this->assignedRides->whereIn('status', [
                    RideStatusEnum::CANCELLED_BY_USER,
                    RideStatusEnum::CANCELLED_BY_DRIVER,
                    RideStatusEnum::SYSTEM_CANCELLED,
                    RideStatusEnum::EXPIRED,
                ])->count()
                : 0,
            'rides' => $this->whenLoaded(
                'assignedRides',
                fn() =>
                $this->assignedRides->values()
            ),

            'review_list' => $reviews->map(fn($review) => [
                'id'     => $review->id,
                'rating' => $review->rating,
                'body'   => $review->body,
                'date'   => $review->created_at?->toDateString(),
            ])->values(),
        ];

        if ($lite) {
            return $base + [
                'responseTime' => 'N/A',
                'pricing' => 'Contact',
                'vehicle' => $vehicleName !== '' ? $vehicleName : 'Truck',
                'licensePlate' => 'N/A',
                'maxCapacity' => 'N/A',
                'insurance' => 'N/A',
                'experience' => 'N/A',
                'truck_image_url' => null,
                'driving_license_image_url' => null,
                'legal_documents_url' => null,
            ];
        }

        return $base + [
            'responseTime' => 'N/A',
            'pricing' => 'Contact for price',
            'vehicle' => $vehicleName !== '' ? $vehicleName : 'Truck',
            'licensePlate' => $this->vehicle?->license_plate ?: 'N/A',
            'maxCapacity' => $this->vehicle?->capacity ?: 'N/A',
            'insurance' => strtoupper((string) ($this->vehicle?->insurance_status ?: 'N/A')),
            'experience' => 'N/A',
            'truck_image_url' => $this->vehicle?->truck_image ? storage_url($this->vehicle->truck_image) : null,
            'driving_license_image_url' => $this->vehicle?->driving_license_image ? storage_url($this->vehicle->driving_license_image) : null,
            'legal_documents_url' => $this->vehicle?->legal_documents ? storage_url($this->vehicle->legal_documents) : null,
        ];
    }

    private function resolveInitials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            if ($part !== '') {
                $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            }
        }

        return $initials !== '' ? $initials : 'DR';
    }
}
