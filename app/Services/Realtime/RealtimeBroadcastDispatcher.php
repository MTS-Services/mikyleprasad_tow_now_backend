<?php

declare(strict_types=1);

namespace App\Services\Realtime;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Central entry point for queueing domain events that broadcast over websockets.
 *
 * Events must implement {@see ShouldBroadcast} and {@see ShouldQueue}. Prefer also
 * {@see ShouldDispatchAfterCommit} on the event class when
 * the broadcast follows a database write.
 */
final class RealtimeBroadcastDispatcher
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function queue(ShouldBroadcast&ShouldQueue $event): void
    {
        $this->events->dispatch($event);
    }
}
