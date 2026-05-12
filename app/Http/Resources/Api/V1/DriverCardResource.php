<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\AccountStatus;
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

        $reviews = $this->assignedRides
            ->pluck('review')
            ->filter();

        // Calculate ride statistics
        $totalRides = $this->assignedRides->count();
        $completedRides = $this->assignedRides->filter(function ($ride) {
            return $ride->status?->isCompleted() ?? false;
        })->count();
        $cancelledRides = $this->assignedRides->filter(function ($ride) {
            return $ride->status?->isCancelled() ?? false;
        })->count();


        $base = [
            'id' => $this->id,
            'initials' => $this->resolveInitials(),
            'name' => $this->name,
            'rating' => 0,
            'reviews' => $reviews->count(),
            'location' => $this->address ?: 'Location unavailable',
            'status' => $status,
            'phoneNumber' => $this->phone,
            'avatar_url' => null,
            'totalRides' => $totalRides,
            'completedRides' => $completedRides,
            'canceledRides' => $cancelledRides,
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
            'truck_image' => $this->vehicle?->truck_image,

            'review' => $reviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'body' => $review->body,
                    'created_at' => $review->created_at?->diffForHumans(),
                    'user' => [
                        'name' => $review->user?->name,
                        'avatar_url' => $review->user?->avatar_url,
                    ],
                ];
            })->values(),
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
