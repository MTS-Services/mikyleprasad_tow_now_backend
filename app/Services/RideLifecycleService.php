<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RideCancelledByEnum;
use App\Enums\RideHistoryTypeEnum;
use App\Enums\RideStatusEnum;
use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\ConversationActivityLog;
use App\Models\Ride;
use App\Models\RideHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RideLifecycleService
{
    public function __construct(
        private readonly UserNotificationService $userNotificationService,
    ) {}

    /**
     * @param  array{driver_id: int, pickup_location: string, dropoff_location: string, notes?: ?string}  $data
     */
    public function createRequest(User $user, array $data): Ride
    {
        if ($user->role !== UserRole::USER) {
            throw new HttpException(403, __('auth.unauthorized'));
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
                'status' => RideStatusEnum::REQUESTED->value,
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
            $this->logRideHistory($ride, $user, RideHistoryTypeEnum::STATUS, null, RideStatusEnum::REQUESTED);

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
        if ($ride->status !== RideStatusEnum::REQUESTED) {
            throw new HttpException(422, 'Only requested rides can be accepted.');
        }
        $this->assertNotSystemCancelled($ride);

        return DB::transaction(function () use ($ride, $driver, $etaMinutes): Ride {
            $from = $ride->status;
            $ride->forceFill([
                'status' => RideStatusEnum::ACCEPTED->value,
                'eta_minutes' => $etaMinutes,
                'accepted_at' => now(),
            ])->save();

            $this->cancelCompetingRequestedRides($ride);
            $this->logRideHistory($ride, $driver, RideHistoryTypeEnum::STATUS, $from, RideStatusEnum::ACCEPTED);
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
        if (! in_array($ride->status, [RideStatusEnum::ACCEPTED, RideStatusEnum::ARRIVED], true)) {
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

        if (! in_array($ride->status, [RideStatusEnum::ACCEPTED, RideStatusEnum::PICKED_UP], true)) {
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
            ->where('status', RideStatusEnum::REQUESTED->value)
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

                $this->logRideHistory($ride, null, RideHistoryTypeEnum::STATUS, RideStatusEnum::REQUESTED, RideStatusEnum::EXPIRED, 'expired');
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
            ->where('status', RideStatusEnum::REQUESTED->value)
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
                RideStatusEnum::REQUESTED,
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
