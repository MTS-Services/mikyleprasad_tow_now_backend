<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideDriverLocationBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $rideId,
        public float $lat,
        public float $lng,
        public ?float $speed,
        public ?float $heading
    ) {}

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
        return 'driver.location';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'lat' => round($this->lat, 6),
            'lng' => round($this->lng, 6),
            'speed' => $this->speed !== null ? round($this->speed, 2) : null,
            'heading' => $this->heading !== null ? round($this->heading, 2) : null,
            'ts' => now()->toIso8601String(),
        ];
    }
}
