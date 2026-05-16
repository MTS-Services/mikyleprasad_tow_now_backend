<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight payload for map polling on poor networks.
 *
 * @mixin Ride
 */
class RideTrackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status?->value ?? $this->status,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'driver' => $this->whenLoaded('driver', fn () => [
                'id' => $this->driver?->id,
                'name' => $this->driver?->name,
                'phone' => $this->driver?->phone,
                'current_lat' => $this->driver?->current_lat !== null ? (float) $this->driver->current_lat : null,
                'current_lng' => $this->driver?->current_lng !== null ? (float) $this->driver->current_lng : null,
                'location_updated_at' => $this->driver?->location_updated_at?->toIso8601String(),
            ]),
        ];
    }
}
