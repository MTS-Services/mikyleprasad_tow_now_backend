<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\RideStatusEnum;
use App\Events\RideDriverLocationBroadcast;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\UpdateDriverLocationRequest;
use App\Models\Ride;
use Illuminate\Http\Response;

class DriverLocationController extends Controller
{
    public function update(UpdateDriverLocationRequest $request): Response
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->forceFill([
            'current_lat' => $validated['lat'],
            'current_lng' => $validated['lng'],
            'location_updated_at' => now(),
        ])->save();

        $activeRide = Ride::query()
            ->where('driver_id', $user->id)
            ->whereIn('status', [
                RideStatusEnum::ACTIVE->value,
                RideStatusEnum::ARRIVED->value,
            ])
            ->latest('id')
            ->first();

        if ($activeRide !== null) {
            broadcast(new RideDriverLocationBroadcast(
                $activeRide->id,
                (float) $validated['lat'],
                (float) $validated['lng'],
                isset($validated['speed']) ? (float) $validated['speed'] : null,
                isset($validated['heading']) ? (float) $validated['heading'] : null,
            ))->toOthers();
        }

        return response()->noContent();
    }
}
