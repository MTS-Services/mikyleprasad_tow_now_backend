<?php

declare(strict_types=1);

namespace App\Support\Ride;

use App\Enums\RideHistoryTypeEnum;
use App\Enums\RideStatusEnum;
use App\Models\Ride;
use App\Models\RideHistory;
use Illuminate\Support\Collection;

final class RideTimingPresenter
{
    /**
     * Sum of logged ETA minutes plus remaining minutes until the latest ETA anchor (active rides only).
     */
    public static function totalEstimatedArrivalMinutes(Ride $ride): ?int
    {
        if ($ride->status !== RideStatusEnum::ACTIVE) {
            return null;
        }

        $histories = self::estimatedTimeHistoriesChronological($ride);

        if ($histories->isEmpty()) {
            return null;
        }

        $sumLogged = (int) $histories->sum(fn ($h): int => (int) ($h->time ?? 0));

        $last = $histories->last();
        $lastEtaMinutes = (int) ($last->time ?? 0);
        $anchor = $last->created_at?->copy()->addMinutes($lastEtaMinutes);
        if ($anchor === null) {
            return $sumLogged;
        }

        $remaining = $anchor->isFuture()
            ? (int) now()->diffInMinutes($anchor)
            : 0;

        return $sumLogged + $remaining;
    }

    /**
     * @return Collection<int, RideHistory>
     */
    private static function estimatedTimeHistoriesChronological(Ride $ride): Collection
    {
        if (! $ride->relationLoaded('histories')) {
            $ride->load('histories');
        }

        return $ride->histories
            ->filter(fn ($h) => $h->type === RideHistoryTypeEnum::ESTIMATED_TIME)
            ->sortBy('id')
            ->values();
    }
}
