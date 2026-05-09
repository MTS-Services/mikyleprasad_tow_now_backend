<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ApprovalStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDriverDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isPending    = $this->approval_status?->value === ApprovalStatus::PENDING->value;
        $isSuspended  = (bool) $this->is_suspended;
        $isFeatured   = (bool) $this->is_featured;

        return [
            'id'              => $this->id,
            'username'        => $this->username,
            'name'            => $this->name,
            'email'           => $this->email,
            'phone'           => $this->phone,
            'avatar'          => $this->avatar,
            'address'         => $this->address,
            'approval_status' => $this->approval_status?->value ?? $this->approval_status,
            'is_suspended'    => $this->is_suspended,
            'is_featured'     => $this->is_featured,
            'created_at'      => $this->created_at,

            'documents' => $this->when($isPending, fn() => [
                'driving_license_image' => $this->vehicle?->driving_license_image,
                'insurance_status'      => $this->vehicle?->insurance_status,
            ]),

            'suspension' => $this->when($isSuspended, fn() => [
                'suspended_at'     => $this->suspended_at,
                'suspension_reason' => $this->suspension_reason,
            ]),

            'ride_statistics' => [
                'total_rides'   => $this->total_rides,
                'completed_rides' => $this->completed_rides,
                'cancelled_rides' => $this->cancelled_rides,
                'active_rides' => $this->active_rides,
            ],

            'performance' => $this->when($isFeatured, fn() => [
                'rating'        => 0, 
                'response_time' => 'N/A',
            ]),

            'vehicle' => $this->vehicle ? [
                'id'            => $this->vehicle->id,
                'name'          => $this->vehicle->name,
                'license_plate' => $this->vehicle->license_plate,
                'capacity'      => $this->vehicle->capacity,

                'driving_license_image' => $this->when($isPending, $this->vehicle->driving_license_image),
                'legal_documents'       => $this->when($isPending, $this->vehicle->legal_documents),
            ] : null,
        ];
    }

}
