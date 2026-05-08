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
        $status = ($this->status?->value ?? $this->status) === AccountStatus::ACTIVE->value && ! $this->is_suspended
            ? 'Online'
            : 'Offline';

        $vehicleName = $this->vehicle?->name
            ?: trim(sprintf('%s %s', (string) ($this->vehicle?->brand ?? ''), (string) ($this->vehicle?->model ?? '')));

        return [
            'id' => $this->id,
            'initials' => $this->resolveInitials(),
            'name' => $this->name,
            'rating' => 0,
            'reviews' => 0,
            'location' => $this->address ?: 'Location unavailable',
            'status' => $status,
            'responseTime' => 'N/A',
            'pricing' => 'Contact for price',
            'vehicle' => $vehicleName !== '' ? $vehicleName : 'Truck',
            'licensePlate' => $this->vehicle?->license_plate ?: 'N/A',
            'maxCapacity' => $this->vehicle?->capacity ?: 'N/A',
            'insurance' => strtoupper((string) ($this->vehicle?->insurance_status ?: 'N/A')),
            'experience' => 'N/A',
            'phoneNumber' => $this->phone,
            'avatar_url' => null,
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
