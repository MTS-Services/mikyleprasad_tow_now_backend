<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\RideHistoryTypeEnum;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ride
 */
class RideResource extends JsonResource
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
            'pickup_location' => $this->pickup_location,
            'dropoff_location' => $this->dropoff_location,
            'notes' => $this->notes,
            'eta_minutes' => $this->eta_minutes,
            'eta_reason' => $this->eta_reason,
            'cancel_reason' => $this->cancel_reason,
            'cancelled_by' => $this->cancelled_by?->value ?? $this->cancelled_by,
            'conversation_id' => $this->whenLoaded('conversation', fn () => $this->conversation?->id),
            'expires_at' => $this->expired_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'picked_up_at' => $this->picked_up_at?->toIso8601String(),
            'completion_requested_at' => $this->completion_requested_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'total_arrival_minutes' => $this->total_arrival_minutes,
            'total_ride_minutes' => $this->total_ride_minutes,
            'timeline' => [
                'eta_updates_count' => $this->whenLoaded(
                    'histories',
                    fn () => $this->histories->where('type', RideHistoryTypeEnum::ESTIMATED_TIME)->count()
                ),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'phone' => $this->user?->phone,
            ]),
            'driver' => $this->whenLoaded('driver', fn () => [
                'id' => $this->driver?->id,
                'name' => $this->driver?->name,
                'phone' => $this->driver?->phone,
                'address' => $this->driver?->address,
            ]),
        ];
    }
}
