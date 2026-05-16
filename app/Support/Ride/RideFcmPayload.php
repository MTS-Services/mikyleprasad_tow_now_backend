<?php

declare(strict_types=1);

namespace App\Support\Ride;

use App\Models\Ride;

/**
 * Canonical FCM data keys for ride notifications (foreground + background handlers).
 * Navigate paths match the React SPA routes (numeric ride id in URL today).
 */
final class RideFcmPayload
{
    /**
     * @return array<string, string>
     */
    public static function forRide(Ride $ride, string $event, string $navigateTo): array
    {
        return [
            'ride_uuid' => (string) $ride->uuid,
            'ride_id' => (string) $ride->id,
            'event' => $event,
            'navigate_to' => $navigateTo,
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public static function merge(Ride $ride, string $event, string $navigateTo, array $base = []): array
    {
        return array_merge($base, self::forRide($ride, $event, $navigateTo));
    }
}
