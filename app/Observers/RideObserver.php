<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\RideStatusBroadcast;
use App\Models\Ride;

class RideObserver
{
    public function created(Ride $ride): void
    {
        $status = $ride->status instanceof \BackedEnum ? $ride->status->value : (string) $ride->status;
        event(new RideStatusBroadcast($ride->id, $status));
    }

    public function updated(Ride $ride): void
    {
        if (! $ride->wasChanged('status')) {
            return;
        }

        $status = $ride->status instanceof \BackedEnum ? $ride->status->value : (string) $ride->status;
        event(new RideStatusBroadcast($ride->id, $status));
    }
}
