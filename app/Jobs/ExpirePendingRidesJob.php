<?php

namespace App\Jobs;

use App\Services\RideLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpirePendingRidesJob implements ShouldQueue
{
    use Queueable;

    public function handle(RideLifecycleService $rideLifecycleService): void
    {
        $rideLifecycleService->expirePendingRides();
    }
}
