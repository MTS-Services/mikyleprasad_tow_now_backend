<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\RideStatusEnum;
use App\Models\Ride;
use App\Support\Ride\RideTimingPresenter;
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
            'pickup_lat' => $this->pickup_lat !== null ? (float) $this->pickup_lat : null,
            'pickup_lng' => $this->pickup_lng !== null ? (float) $this->pickup_lng : null,
            'dropoff_lat' => $this->dropoff_lat !== null ? (float) $this->dropoff_lat : null,
            'dropoff_lng' => $this->dropoff_lng !== null ? (float) $this->dropoff_lng : null,
            'offline_temp_id' => $this->offline_temp_id,
            'synced_from_offline' => $this->synced_from_offline,
            'problem_type' => $this->problem_type,
            'problem_description' => $this->problem_description,
            'estimated_price' => $this->estimated_price !== null ? (float) $this->estimated_price : null,
            'final_price' => $this->final_price !== null ? (float) $this->final_price : null,
            'payment_status' => $this->payment_status,
            'eta_minutes' => $this->eta_minutes,
            'eta_reason' => $this->eta_reason,
            'cancel_reason' => $this->cancel_reason,
            'cancelled_by' => $this->cancelled_by?->value ?? $this->cancelled_by,
            'conversation_id' => $this->whenLoaded('conversation', fn() => $this->conversation?->id),
            'expires_at' => $this->expired_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'completion_requested_at' => $this->completion_requested_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'total_estimated_arrival_minutes' => $this->when(
                $this->status === RideStatusEnum::ACTIVE,
                fn() => RideTimingPresenter::totalEstimatedArrivalMinutes($this->resource)
            ),
            'total_arrival_minutes' => $this->when(
                in_array($this->status, [RideStatusEnum::ARRIVED, RideStatusEnum::COMPLETED_USER], true),
                fn() => $this->total_arrival_minutes
            ),
            'total_ride_minutes' => $this->when(
                $this->status === RideStatusEnum::COMPLETED_USER,
                fn() => $this->total_ride_minutes
            ),
            'timeline' => $this->whenLoaded(
                'histories',
                fn($histories) => $histories->map(function ($history) {
                    return [
                        'id' => $history->id,
                        'actor_user_id' => $history->user_id,
                        'type' => $history->type?->value ?? $history->type ?? null,
                        'from_status' => $history->from_status?->value ?? $history->from_status ?? null,
                        'to_status' => $history->to_status?->value ?? $history->to_status ?? null,
                        'time' => $history->time ?? null,
                        'reason' => $history->reason ?? null,
                        'data' => $history->data ?? null,
                        'created_at' => $history->created_at?->toIso8601String() ?? $history->created_at,
                        'updated_at' => $history->updated_at?->toIso8601String() ?? $history->updated_at,
                    ];
                }),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'phone' => $this->user?->phone,
            ]),
            'driver' => $this->whenLoaded('driver', fn() => [
                'id' => $this->driver?->id,
                'name' => $this->driver?->name,
                'phone' => $this->driver?->phone,
                'address' => $this->driver?->address,
                'avatar_url' => storage_url($this->driver?->avatar),
            ]),
            'review' => $this->whenLoaded('review', fn() => $this->review),
        ];
    }
}
