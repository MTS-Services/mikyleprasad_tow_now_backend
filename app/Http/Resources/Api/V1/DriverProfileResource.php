<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverProfileResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'address' => $this->address,
            'created_at' => $this->created_at,
            'approval_status' => $this->approval_status,

            'vehicle' => $this->vehicle ? [
                'id'                    => $this->vehicle->id,
                'name'                  => $this->vehicle->name,
                'capacity'              => $this->vehicle->capacity,
                'license_plate'         => $this->vehicle->license_plate,
                'truck_image'           => $this->vehicle->truck_image,
                'insurance_status'      => $this->vehicle->insurance_status,
            ] : null,
        ];
    }
}
