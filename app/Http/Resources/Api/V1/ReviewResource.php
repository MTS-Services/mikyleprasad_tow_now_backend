<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ride_id' => $this->ride_id,

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'email' => $this->user?->email,
                    'avatar_url' => storage_url($this->user->avatar),
                ];
            }),

            'driver' => $this->whenLoaded('ride.driver', function () {
                return [
                    'id' => $this->ride?->driver?->id,
                    'name' => $this->ride?->driver?->name,
                    'email' => $this->ride?->driver?->email,
                    'avatar_url' => storage_url($this->ride?->driver?->avatar),
                ];
            }),

            'rating' => $this->rating,
            'body' => $this->body,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
