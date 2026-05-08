<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\RideCancelledByEnum;
use App\Enums\RideHistoryTypeEnum;
use App\Enums\RideStatusEnum;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\ConversationActivityLog;
use App\Models\Ride;
use App\Models\RideHistory;
use App\Models\User;
use App\Support\Filters\RideQueryFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RideLifecycleService
{
    public function __construct(
        private readonly UserNotificationService $userNotificationService,
        private readonly RideQueryFilters $rideQueryFilters,
    ) {}

    public function getStats(User $user)
    {
        return [
            'pending' => Ride::query()->where('user_id', $user->id)->where('status', RideStatusEnum::PENDING->value)->count(),
            'active' => Ride::query()->where('user_id', $user->id)->whereIn('status', [
                RideStatusEnum::ACTIVE->value,
                RideStatusEnum::ARRIVED->value,
                RideStatusEnum::PICKED_UP->value,
                RideStatusEnum::COMPLETED_DRIVER_PENDING_USER->value,
            ])->count(),
            'completed' => Ride::query()->where('user_id', $user->id)->where('status', RideStatusEnum::COMPLETED_USER->value)->count(),
            'cancelled' => Ride::query()->where('user_id', $user->id)->whereIn('status', [
                RideStatusEnum::CANCELLED_BY_DRIVER->value,
                RideStatusEnum::CANCELLED_BY_USER->value,
                RideStatusEnum::EXPIRED->value,
            ])->count(),
            'expired' => Ride::query()->where('user_id', $user->id)->where('status', RideStatusEnum::EXPIRED->value)->count(),
            'total' => Ride::query()->where('user_id', $user->id)->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)->count(),
        ];
    }

    public function getRide(string $value, string $column = 'id', ?array $filters = null): Ride
    {
        $query = Ride::query()->where($column, $value);
        $query = $this->rideQueryFilters->apply($query, $filters ?? []);

        return $query->firstOrFail();
    }

    public function getRides(User $user, ?array $validated = null): LengthAwarePaginator
    {
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $pageName = $validated['page_name'] ?? 'page';

        $query = Ride::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)
            ->with(['driver', 'conversation']);

        $rides = $this->rideQueryFilters
            ->apply($query, $validated)
            ->paginate(perPage: $perPage, page: $page, pageName: $pageName)
            ->withQueryString();

        return $rides;
    }

    /**
     * @param  array{driver_id: int, pickup_location: string, dropoff_location: string, notes?: ?string}  $data
     */
    public function createRequest(User $user, array $data): Ride
    {
        if ($user->role !== UserRole::USER) {
            throw new HttpException(403, __('auth.unauthorized'));
        }

        // check if user has an active ride request
        $activeRide = Ride::query()->where('user_id', $user->id)->where('status', RideStatusEnum::ACTIVE->value)->first();
        if ($activeRide) {
            throw new HttpException(422, 'You already have an active ride request.');
        }

        // Check if any pending ride request for the selected driver
        $pendingRideRequest = Ride::query()
            ->where('driver_id', $data['driver_id'])
            ->where('status', RideStatusEnum::PENDING->value)
            ->first();
        if ($pendingRideRequest) {
            throw new HttpException(422, 'You already have a pending ride request for the selected driver.');
        }

        $driver = User::query()
            ->whereKey($data['driver_id'])
            ->where('role', UserRole::DRIVER->value)
            ->where('approval_status', ApprovalStatus::APPROVED->value)
            ->where('is_suspended', false)
            ->first();

        if (! $driver) {
            throw new HttpException(422, 'Selected driver is invalid.');
        }

        return DB::transaction(function () use ($user, $driver, $data): Ride {
            $ride = Ride::query()->create([
                'user_id' => $user->id,
                'driver_id' => $driver->id,
                'status' => RideStatusEnum::PENDING->value,
                'pickup_location' => $data['pickup_location'],
                'dropoff_location' => $data['dropoff_location'],
                'notes' => $data['notes'] ?? null,
                'expired_at' => now()->addMinutes((int) config('rides.request_expire_minutes', 10)),
            ]);

            $conversation = Conversation::query()->create([
                'ride_id' => $ride->id,
                'name' => "Ride #{$ride->id}",
                'created_by' => $user->id,
            ]);
            $conversation->participants()->attach([$user->id, $driver->id]);

            $this->logConversationActivity($conversation, $user->id, 'ride.requested', [
                'ride_id' => $ride->id,
            ]);
            $this->logRideHistory($ride, $user, RideHistoryTypeEnum::STATUS, null, RideStatusEnum::PENDING);

            $this->userNotificationService->notify(
                recipient: $driver,
                type: 'ride.requested',
                title: 'New ride request',
                body: "{$user->name} sent you a ride request.",
                data: [
                    'ride_id' => $ride->id,
                    'conversation_id' => $conversation->id,
                ],
                sender: $user
            );

            return $ride->load(['user', 'driver', 'conversation']);
        });
    }

    public function updateStatus(Ride $ride, User $user, RideStatusEnum $status): Ride
    {

        // First check is the ride is owned by the user or the driver
        if (! $ride->user_id == $user->id || ! $ride->driver_id == $user->id) {
            throw new HttpException(403, __('auth.unauthorized'));
        }
        // if status complete or cancelled, throw an error
        if ($status->isTerminal()) {
            throw new HttpException(422, 'Ride is already completed or cancelled.');
        }

        // if the status is completed, we need to check if the user has already completed the ride
        if ($status->isCompleted()) {
            throw new HttpException(422, 'Ride is already completed.');
        }
        if ($status->isCancelled()) {
            throw new HttpException(422, 'Ride is already cancelled.');
        }
        $ride->lockForUpdate();
        $from = $ride->status;

        // update the ride status
        $ride->forceFill([
            'status' => $status,
        ])->save();

        $this->logRideHistory($ride, $user, RideHistoryTypeEnum::STATUS, $from, $status, null, null);
        $this->logConversationActivity($ride->conversation, $user->id, 'ride.status_updated', [
            'status' => $status,
        ]);

        return $ride->load(['user', 'driver', 'conversation']);
    }

    public function cancelByUser(Ride $ride, User $user, ?string $reason = null): Ride
    {
        $this->assertParticipant($ride, $user);
        $this->assertNotSystemCancelled($ride);
        $this->assertTransitionable($ride);

        return DB::transaction(function () use ($ride, $user, $reason): Ride {
            $from = $ride->status;
            $ride->forceFill([
                'status' => RideStatusEnum::CANCELLED_BY_USER->value,
                'cancel_reason' => $reason,
                'cancelled_by' => RideCancelledByEnum::USER->value,
                'cancelled_at' => now(),
            ])->save();

            $this->logRideHistory($ride, $user, RideHistoryTypeEnum::STATUS, $from, RideStatusEnum::CANCELLED_BY_USER, $reason);
            $this->logConversationActivity($ride->conversation, $user->id, 'ride.cancelled_by_user', ['reason' => $reason]);

            $this->userNotificationService->notify(
                recipient: $ride->driver,
                type: 'ride.cancelled_by_user',
                title: 'Ride cancelled',
                body: "{$ride->user->name} cancelled the ride request.",
                data: ['ride_id' => $ride->id],
                sender: $user
            );

            return $ride->load(['user', 'driver', 'conversation']);
        });
    }

    public function acceptByDriver(Ride $ride, User $driver, int $etaMinutes): Ride
    {
        if ($ride->driver_id !== $driver->id) {
            throw new HttpException(403, __('auth.unauthorized'));
        }
        if ($ride->status !== RideStatusEnum::PENDING) {
            throw new HttpException(422, 'Only pending rides can be accepted.');
        }
        $this->assertNotSystemCancelled($ride);

        return DB::transaction(function () use ($ride, $driver, $etaMinutes): Ride {
            $from = $ride->status;
            $ride->forceFill([
                'status' => RideStatusEnum::ACTIVE->value,
                'eta_minutes' => $etaMinutes,
                'accepted_at' => now(),
            ])->save();

            $this->cancelCompetingRequestedRides($ride);
            $this->logRideHistory($ride, $driver, RideHistoryTypeEnum::STATUS, $from, RideStatusEnum::ACTIVE);
            $this->logRideHistory($ride, $driver, RideHistoryTypeEnum::ESTIMATED_TIME, null, null, null, $etaMinutes);
            $this->logConversationActivity($ride->conversation, $driver->id, 'ride.accepted', [
                'eta_minutes' => $etaMinutes,
            ]);

            $this->userNotificationService->notify(
                recipient: $ride->user,
                type: 'ride.accepted',
                title: 'Ride accepted',
                body: "{$driver->name} accepted your ride request.",
                data: [
                    'ride_id' => $ride->id,
                    'eta_minutes' => $etaMinutes,
                    'conversation_id' => $ride->conversation?->id,
                ],
                sender: $driver
            );

            return $ride->load(['user', 'driver', 'conversation']);
        });
    }

    public function updateEta(Ride $ride, User $driver, int $etaMinutes, string $reason): Ride
    {
        if ($ride->driver_id !== $driver->id) {
            throw new HttpException(403, __('auth.unauthorized'));
        }
        if (! in_array($ride->status, [RideStatusEnum::ACTIVE, RideStatusEnum::ARRIVED], true)) {
            throw new HttpException(422, 'ETA can only be updated for accepted/arrived rides.');
        }
        $this->assertNotSystemCancelled($ride);

        return DB::transaction(function () use ($ride, $driver, $etaMinutes, $reason): Ride {
            $ride->forceFill([
                'eta_minutes' => $etaMinutes,
                'eta_reason' => $reason,
            ])->save();

            $this->logRideHistory($ride, $driver, RideHistoryTypeEnum::ESTIMATED_TIME, null, null, $reason, $etaMinutes);
            $this->logConversationActivity($ride->conversation, $driver->id, 'ride.eta_updated', [
                'eta_minutes' => $etaMinutes,
                'reason' => $reason,
            ]);

            $this->userNotificationService->notify(
                recipient: $ride->user,
                type: 'ride.eta_updated',
                title: 'ETA updated',
                body: "{$driver->name} updated the arrival estimate.",
                data: [
                    'ride_id' => $ride->id,
                    'eta_minutes' => $etaMinutes,
                    'reason' => $reason,
                ],
                sender: $driver
            );

            return $ride->load(['user', 'driver', 'conversation']);
        });
    }

    public function markArrived(Ride $ride, User $actor): Ride
    {
        $this->assertParticipant($ride, $actor);
        $this->assertNotSystemCancelled($ride);

        if (! in_array($ride->status, [RideStatusEnum::ACTIVE, RideStatusEnum::PICKED_UP], true)) {
            throw new HttpException(422, 'Ride is not in a state that can be marked arrived.');
        }

        return DB::transaction(function () use ($ride, $actor): Ride {
            $from = $ride->status;
            $arrivalMinutes = $ride->accepted_at ? max(0, now()->diffInMinutes($ride->accepted_at)) : null;

            $ride->forceFill([
                'status' => RideStatusEnum::ARRIVED->value,
                'arrived_at' => now(),
                'total_arrival_minutes' => $arrivalMinutes,
            ])->save();

            $this->logRideHistory($ride, $actor, RideHistoryTypeEnum::STATUS, $from, RideStatusEnum::ARRIVED);
            $this->logConversationActivity($ride->conversation, $actor->id, 'ride.arrived', [
                'total_arrival_minutes' => $arrivalMinutes,
            ]);

            return $ride->load(['user', 'driver', 'conversation']);
        });
    }

    public function markPickedUp(Ride $ride, User $actor): Ride
    {
        $this->assertParticipant($ride, $actor);
        $this->assertNotSystemCancelled($ride);

        if ($ride->status !== RideStatusEnum::ARRIVED) {
            throw new HttpException(422, 'Ride must be arrived before pick-up.');
        }

        return DB::transaction(function () use ($ride, $actor): Ride {
            $from = $ride->status;
            $ride->forceFill([
                'status' => RideStatusEnum::PICKED_UP->value,
                'picked_up_at' => now(),
            ])->save();

            $this->logRideHistory($ride, $actor, RideHistoryTypeEnum::STATUS, $from, RideStatusEnum::PICKED_UP);
            $this->logConversationActivity($ride->conversation, $actor->id, 'ride.picked_up');

            return $ride->load(['user', 'driver', 'conversation']);
        });
    }

    public function completeByUser(Ride $ride, User $user): Ride
    {
        if ($ride->user_id !== $user->id) {
            throw new HttpException(403, __('auth.unauthorized'));
        }
        $this->assertNotSystemCancelled($ride);
        if (! in_array($ride->status, [RideStatusEnum::PICKED_UP, RideStatusEnum::COMPLETED_DRIVER_PENDING_USER], true)) {
            throw new HttpException(422, 'Ride is not ready to complete.');
        }

        return DB::transaction(function () use ($ride, $user): Ride {
            $from = $ride->status;
            $rideMinutes = $ride->picked_up_at ? max(0, now()->diffInMinutes($ride->picked_up_at)) : null;

            $ride->forceFill([
                'status' => RideStatusEnum::COMPLETED_USER->value,
                'completed_at' => now(),
                'total_ride_minutes' => $rideMinutes,
            ])->save();

            $this->logRideHistory($ride, $user, RideHistoryTypeEnum::COMPLETE, $from, RideStatusEnum::COMPLETED_USER, null, $rideMinutes);
            $this->logConversationActivity($ride->conversation, $user->id, 'ride.completed_by_user', [
                'total_ride_minutes' => $rideMinutes,
            ]);

            $this->userNotificationService->notify(
                recipient: $ride->driver,
                type: 'ride.completed',
                title: 'Ride completed',
                body: "{$user->name} marked the ride as completed.",
                data: ['ride_id' => $ride->id],
                sender: $user
            );

            return $ride->load(['user', 'driver', 'conversation']);
        });
    }

    public function requestCompletionByDriver(Ride $ride, User $driver): Ride
    {
        if ($ride->driver_id !== $driver->id) {
            throw new HttpException(403, __('auth.unauthorized'));
        }
        $this->assertNotSystemCancelled($ride);
        if ($ride->status !== RideStatusEnum::PICKED_UP) {
            throw new HttpException(422, 'Only picked-up rides can request completion.');
        }

        return DB::transaction(function () use ($ride, $driver): Ride {
            $from = $ride->status;
            $ride->forceFill([
                'status' => RideStatusEnum::COMPLETED_DRIVER_PENDING_USER->value,
                'completion_requested_at' => now(),
            ])->save();

            $this->logRideHistory($ride, $driver, RideHistoryTypeEnum::STATUS, $from, RideStatusEnum::COMPLETED_DRIVER_PENDING_USER);
            $this->logConversationActivity($ride->conversation, $driver->id, 'ride.completion_requested');

            $this->userNotificationService->notify(
                recipient: $ride->user,
                type: 'ride.completion_requested',
                title: 'Completion confirmation required',
                body: "{$driver->name} requested completion confirmation for your ride.",
                data: ['ride_id' => $ride->id],
                sender: $driver
            );

            return $ride->load(['user', 'driver', 'conversation']);
        });
    }

    public function expirePendingRides(): int
    {
        $rides = Ride::query()
            ->where('status', RideStatusEnum::PENDING->value)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now())
            ->with(['user', 'driver', 'conversation'])
            ->get();

        foreach ($rides as $ride) {
            DB::transaction(function () use ($ride): void {
                $ride->forceFill([
                    'status' => RideStatusEnum::EXPIRED->value,
                    'cancelled_by' => RideCancelledByEnum::SYSTEM->value,
                    'cancel_reason' => 'expired',
                    'cancelled_at' => now(),
                ])->save();

                $this->logRideHistory($ride, null, RideHistoryTypeEnum::STATUS, RideStatusEnum::PENDING, RideStatusEnum::EXPIRED, 'expired');
                $this->logConversationActivity($ride->conversation, null, 'ride.expired');
            });
        }

        return $rides->count();
    }

    private function cancelCompetingRequestedRides(Ride $acceptedRide): void
    {
        /** @var Collection<int, Ride> $rides */
        $rides = Ride::query()
            ->whereKeyNot($acceptedRide->id)
            ->where('status', RideStatusEnum::PENDING->value)
            ->where(function (Builder $query) use ($acceptedRide): void {
                $query->where('driver_id', $acceptedRide->driver_id)
                    ->orWhere('user_id', $acceptedRide->user_id);
            })
            ->with(['conversation'])
            ->get();

        foreach ($rides as $ride) {
            $ride->forceFill([
                'status' => RideStatusEnum::SYSTEM_CANCELLED->value,
                'cancelled_by' => RideCancelledByEnum::SYSTEM->value,
                'cancel_reason' => 'another_ride_accepted',
                'cancelled_at' => now(),
            ])->save();

            $this->logRideHistory(
                $ride,
                null,
                RideHistoryTypeEnum::STATUS,
                RideStatusEnum::PENDING,
                RideStatusEnum::SYSTEM_CANCELLED,
                'another_ride_accepted'
            );
            $this->logConversationActivity($ride->conversation, null, 'ride.system_cancelled', [
                'reason' => 'another_ride_accepted',
            ]);
        }
    }

    private function assertParticipant(Ride $ride, User $user): void
    {
        if ($ride->user_id !== $user->id && $ride->driver_id !== $user->id) {
            throw new HttpException(403, __('auth.unauthorized'));
        }
    }

    private function assertNotSystemCancelled(Ride $ride): void
    {
        if ($ride->status === RideStatusEnum::SYSTEM_CANCELLED) {
            throw new HttpException(404, 'Ride not found.');
        }
    }

    private function assertTransitionable(Ride $ride): void
    {
        if ($ride->status->isTerminal()) {
            throw new HttpException(422, 'Ride is already finalized.');
        }
    }

    private function logConversationActivity(?Conversation $conversation, ?int $actorId, string $action, array $data = []): void
    {
        if (! $conversation) {
            return;
        }

        ConversationActivityLog::query()->create([
            'conversation_id' => $conversation->id,
            'actor_id' => $actorId,
            'action' => $action,
            'data' => $data === [] ? null : $data,
        ]);
    }

    private function logRideHistory(
        Ride $ride,
        ?User $actor,
        RideHistoryTypeEnum $type,
        RideStatusEnum|string|null $fromStatus = null,
        RideStatusEnum|string|null $toStatus = null,
        ?string $reason = null,
        ?int $time = null
    ): void {
        RideHistory::query()->create([
            'ride_id' => $ride->id,
            'user_id' => $actor?->id,
            'type' => $type->value,
            'from_status' => $fromStatus instanceof RideStatusEnum ? $fromStatus->value : $fromStatus,
            'to_status' => $toStatus instanceof RideStatusEnum ? $toStatus->value : $toStatus,
            'reason' => $reason,
            'time' => $time,
        ]);
    }
}
