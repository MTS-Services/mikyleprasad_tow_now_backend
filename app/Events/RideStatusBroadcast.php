<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideStatusBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldQueue
{
    use Dispatchable;
    use InteractsWithSockets;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $rideId,
        public string $status,
        public array $extra = []
    ) {
        $this->onQueue('default');
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('ride.'.$this->rideId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'status.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge([
            'ride_id' => $this->rideId,
            'status' => $this->status,
            'ts' => now()->toIso8601String(),
        ], $this->extra);
    }
}
