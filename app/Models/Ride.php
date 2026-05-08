<?php

namespace App\Models;

use App\Enums\RideStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'uuid',
    'user_id',
    'driver_id',
    'status',
    'pickup_location',
    'dropoff_location',
    'notes',
    'total_arrival_time',
    'total_ride_time',
    'expired_at',
])]


class Ride extends Model
{
    protected $casts = [
        'expired_at' => 'datetime',
        'status' => RideStatusEnum::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Ride $ride): void {
            if ($ride->uuid === null || $ride->uuid === '') {
                $ride->uuid = generate_uuid();
            }
        });
    }
}
