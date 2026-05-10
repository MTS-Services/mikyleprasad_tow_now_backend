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

    public function getStats(?User $user = null, string $type = 'user')
    {
        return Ride::query()
            // Global filter to exclude system cancelled rides
            ->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)
            // If type is not admin, apply the user/driver filtering logic
            ->when($type !== 'admin', function ($query) use ($user, $type) {
                $column = ($type === 'driver') ? 'driver_id' : 'user_id';
                $query->where($column, $user?->id);
            })
            ->selectRaw('
            COUNT(CASE WHEN status = ? THEN 1 END) as pending,
            COUNT(CASE WHEN status IN (?, ?) THEN 1 END) as active,
            COUNT(CASE WHEN status = ? THEN 1 END) as completed,
            COUNT(CASE WHEN status IN (?, ?, ?, ?) THEN 1 END) as cancelled,
            COUNT(CASE WHEN status = ? THEN 1 END) as expired,
            COUNT(CASE WHEN status != ? THEN 1 END) as total
        ', [
                RideStatusEnum::PENDING->value,
                RideStatusEnum::ACTIVE->value,
                RideStatusEnum::ARRIVED->value,
                RideStatusEnum::COMPLETED_USER->value,
                RideStatusEnum::CANCELLED_BY_DRIVER->value,
                RideStatusEnum::CANCELLED_BY_USER->value,
                RideStatusEnum::SYSTEM_CANCELLED->value,
                RideStatusEnum::EXPIRED->value,
                RideStatusEnum::EXPIRED->value,
                RideStatusEnum::SYSTEM_CANCELLED->value,
            ])
            ->first()
            ->toArray();
    }

    /**
     * @param  array{
     *   value?: ?string,
     *   column?: ?string,
     *   filters?: ?array,
     *   customQuery?: ?array,
     *   with?: ?array
     * }  $params
     */
    public function getRide(array $params = []): Ride
    {
        $value = (string) ($params['value'] ?? '');
        $column = (string) ($params['column'] ?? 'id');
        $filters = (array) ($params['filters'] ?? []);
        $customQuery = (array) ($params['customQuery'] ?? []);
        $with = (array) ($params['with'] ?? []);

        $query = Ride::query()->where($column, $value);
        $query = $this->rideQueryFilters->apply($query, $filters, $customQuery);

        return $query->with($with)->firstOrFail();
    }

    // public function getRides(User $user, ?array $validated = null): LengthAwarePaginator
    // {
    //     $perPage = (int) ($validated['per_page'] ?? 15);
    //     $page = (int) ($validated['page'] ?? 1);
    //     $pageName = $validated['page_name'] ?? 'page';

    //     $query = Ride::query()
    //         ->where('user_id', $user->id)
    //         ->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)
    //         ->with(['driver', 'conversation', 'histories']);

    //     $rides = $this->rideQueryFilters
    //         ->apply($query, $validated)
    //         ->paginate(perPage: $perPage, page: $page, pageName: $pageName)
    //         ->withQueryString();

    //     return $rides;
    // }

    public function getRides(array $params = []): LengthAwarePaginator
    {
        $perPage = (int) ($params['per_page'] ?? 15);
        $page = (int) ($params['page'] ?? 1);
        $pageName = (string) ($params['page_name'] ?? 'page');
        $filters = (array) ($params['filters'] ?? []);
        $customQuery = (array) ($params['customQuery'] ?? []);

        $query = Ride::query()->with(['user', 'driver', 'conversation', 'histories']);
        $query = $this->rideQueryFilters->apply(query: $query, filters: $filters, customQuery: $customQuery);

        return $query->paginate(perPage: $perPage, page: $page, pageName: $pageName)->withQueryString();
    }

    /**
     * @param  array{driver_id: int, pickup_location: string, dropoff_location: string, notes?: ?string}  $data
     */
    public function createRequest(User $user, array $data): Ride
    {
        if ($user->role !== UserRole::USER) {
            throw new HttpException(403, __('auth.unauthorized'));
        }

        return DB::transaction(function () use ($user, $data): Ride {
            $driver = User::query()
                ->whereKey($data['driver_id'])
                ->where('role', UserRole::DRIVER->value)
                ->where('approval_status', ApprovalStatus::APPROVED->value)
                ->where('is_suspended', false)
                ->lockForUpdate()
                ->first();

            if (! $driver) {
                throw new HttpException(422, 'Selected driver is invalid.');
            }

            $userHasActiveRide = Ride::query()
                ->where('user_id', $user->id)
                ->whereIn('status', RideStatusEnum::inProgressRideStatuses())
                ->lockForUpdate()
                ->exists();

            if ($userHasActiveRide) {
                throw new HttpException(422, 'You already have an active ride.');
            }

            $driverHasActiveRide = Ride::query()
                ->where('driver_id', $driver->id)
                ->whereIn('status', RideStatusEnum::inProgressRideStatuses())
                ->lockForUpdate()
                ->exists();

            if ($driverHasActiveRide) {
                throw new HttpException(422, 'Selected driver is currently unavailable.');
            }

            $hasPendingForSameDriver = Ride::query()
                ->where('user_id', $user->id)
                ->where('driver_id', $driver->id)
                ->where('status', RideStatusEnum::PENDING->value)
                ->lockForUpdate()
                ->exists();

            if ($hasPendingForSameDriver) {
                throw new HttpException(422, 'You already have a pending request for this driver.');
            }

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

            $this->userNotificationService->notify(
                recipient: $user,
                type: 'ride.request_sent',
                title: 'Ride request sent',
                body: 'Your ride request was sent to the driver.',
                data: [
                    'ride_id' => $ride->id,
                    'conversation_id' => $conversation->id,
                ],
                sender: null
            );

            return $ride->load(['user', 'driver', 'conversation', 'histories']);
        });
    }

    public function cancelByUser(Ride $ride, User $user, ?string $reason = null): Ride
    {
        if ($ride->user_id !== $user->id) {
            throw new HttpException(403, __('auth.unauthorized'));
        }

        return DB::transaction(function () use ($ride, $user, $reason): Ride {
            $ride = Ride::query()
                ->whereKey($ride->id)
                ->with(['user', 'driver', 'conversation', 'histories'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCancelableStatus($ride);

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

            return $ride->load(['user', 'driver', 'conversation', 'histories']);
        });
    }

    public function cancelByDriver(Ride $ride, User $driver, ?string $reason = null): Ride
    {
        if ($ride->driver_id !== $driver->id) {
            throw new HttpException(403, __('auth.unauthorized'));
        }

        return DB::transaction(function () use ($ride, $driver, $reason): Ride {
            $ride = Ride::query()
                ->whereKey($ride->id)
                ->with(['user', 'driver', 'conversation', 'histories'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCancelableStatus($ride);

            $from = $ride->status;
            $ride->forceFill([
                'status' => RideStatusEnum::CANCELLED_BY_DRIVER->value,
                'cancel_reason' => $reason,
                'cancelled_by' => RideCancelledByEnum::DRIVER->value,
                'cancelled_at' => now(),
            ])->save();

            $this->logRideHistory($ride, $driver, RideHistoryTypeEnum::STATUS, $from, RideStatusEnum::CANCELLED_BY_DRIVER, $reason);
            $this->logConversationActivity($ride->conversation, $driver->id, 'ride.cancelled_by_driver', ['reason' => $reason]);

            $this->userNotificationService->notify(
                recipient: $ride->user,
                type: 'ride.cancelled_by_driver',
                title: 'Ride cancelled',
                body: "{$ride->driver->name} cancelled the ride.",
                data: ['ride_id' => $ride->id],
                sender: $driver
            );

            return $ride->load(['user', 'driver', 'conversation', 'histories']);
        });
    }

    public function acceptByDriver(Ride $ride, User $driver, int $etaMinutes): Ride
    {
        return DB::transaction(function () use ($ride, $driver, $etaMinutes): Ride {
            $ride = Ride::query()
                ->whereKey($ride->id)
                ->with(['user', 'driver', 'conversation', 'histories'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($ride->driver_id !== $driver->id) {
                throw new HttpException(403, __('auth.unauthorized'));
            }
            if ($ride->status !== RideStatusEnum::PENDING) {
                throw new HttpException(422, 'Only pending rides can be accepted.');
            }

            $driverHasAnotherActiveRide = Ride::query()
                ->where('driver_id', $driver->id)
                ->whereKeyNot($ride->id)
                ->whereIn('status', RideStatusEnum::inProgressRideStatuses())
                ->lockForUpdate()
                ->exists();

            if ($driverHasAnotherActiveRide) {
                throw new HttpException(422, 'You already have an active ride.');
            }

            $userHasAnotherActiveRide = Ride::query()
                ->where('user_id', $ride->user_id)
                ->whereKeyNot($ride->id)
                ->whereIn('status', RideStatusEnum::inProgressRideStatuses())
                ->lockForUpdate()
                ->exists();

            if ($userHasAnotherActiveRide) {
                throw new HttpException(422, 'This user already has an active ride.');
            }

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

            return $ride->load(['user', 'driver', 'conversation', 'histories']);
        });
    }

    public function updateEta(Ride $ride, User $driver, int $etaMinutes, string $reason): Ride
    {
        if ($ride->driver_id !== $driver->id) {
            throw new HttpException(403, __('auth.unauthorized'));
        }
        if ($ride->status !== RideStatusEnum::ACTIVE) {
            throw new HttpException(422, 'ETA can only be updated for active rides.');
        }
        if ($reason === '') {
            throw new HttpException(422, 'Reason is required.');
        }

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

            return $ride->load(['user', 'driver', 'conversation', 'histories']);
        });
    }

    public function markArrived(Ride $ride, User $actor): Ride
    {
        if ($ride->user_id !== $actor->id && $ride->driver_id !== $actor->id) {
            throw new HttpException(403, __('auth.unauthorized'));
        }

        return DB::transaction(function () use ($ride, $actor): Ride {
            $ride = Ride::query()
                ->whereKey($ride->id)
                ->with(['user', 'driver', 'conversation', 'histories'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($ride->status === RideStatusEnum::ARRIVED) {
                return $ride->load(['user', 'driver', 'conversation', 'histories']);
            }

            if ($ride->status !== RideStatusEnum::ACTIVE) {
                throw new HttpException(422, 'Only active rides can be marked as arrived.');
            }

            $from = $ride->status;
            $totalArrivalMinutes = $ride->accepted_at !== null
                ? max(0, (int) $ride->accepted_at->diffInMinutes(now()))
                : null;

            $ride->forceFill([
                'status' => RideStatusEnum::ARRIVED->value,
                'arrived_at' => now(),
                'total_arrival_minutes' => $totalArrivalMinutes,
            ])->save();

            $this->logRideHistory($ride, $actor, RideHistoryTypeEnum::STATUS, $from, RideStatusEnum::ARRIVED);
            $this->logConversationActivity($ride->conversation, $actor->id, 'ride.arrived', [
                'ride_id' => $ride->id,
            ]);

            $this->userNotificationService->notify(
                recipient: $ride->user,
                type: 'ride.arrived',
                title: 'Driver arrived',
                body: 'The ride was marked as arrived at the pickup location.',
                data: ['ride_id' => $ride->id],
                sender: $actor
            );

            $this->userNotificationService->notify(
                recipient: $ride->driver,
                type: 'ride.arrived',
                title: 'Driver arrived',
                body: 'The ride was marked as arrived at the pickup location.',
                data: ['ride_id' => $ride->id],
                sender: $actor
            );

            return $ride->load(['user', 'driver', 'conversation', 'histories']);
        });
    }

    public function completeByUser(Ride $ride, User $user): Ride
    {
        if ($ride->user_id !== $user->id) {
            throw new HttpException(403, __('auth.unauthorized'));
        }
        if ($ride->status !== RideStatusEnum::ARRIVED) {
            throw new HttpException(422, 'Ride must be marked arrived before it can be completed.');
        }

        return DB::transaction(function () use ($ride, $user): Ride {
            $from = $ride->status;
            $rideMinutes = $ride->arrived_at !== null
                ? max(0, (int) $ride->arrived_at->diffInMinutes(now()))
                : null;

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

            $this->notifyAdminsRideCompleted($ride);

            return $ride->load(['user', 'driver', 'conversation', 'histories']);
        });
    }

    public function completeByDriver(Ride $ride, User $driver): Ride
    {
        if ($ride->driver_id !== $driver->id) {
            throw new HttpException(403, __('auth.unauthorized'));
        }
        if ($ride->status !== RideStatusEnum::ARRIVED) {
            throw new HttpException(422, 'Ride must be marked arrived before it can be completed.');
        }

        return DB::transaction(function () use ($ride, $driver): Ride {
            $from = $ride->status;
            $rideMinutes = $ride->arrived_at !== null
                ? max(0, (int) $ride->arrived_at->diffInMinutes(now()))
                : null;

            $ride->forceFill([
                'status' => RideStatusEnum::COMPLETED_USER->value,
                'completed_at' => now(),
                'total_ride_minutes' => $rideMinutes,
            ])->save();

            $this->logRideHistory($ride, $driver, RideHistoryTypeEnum::COMPLETE, $from, RideStatusEnum::COMPLETED_USER, null, $rideMinutes);
            $this->logConversationActivity($ride->conversation, $driver->id, 'ride.completed_by_driver', [
                'total_ride_minutes' => $rideMinutes,
            ]);

            $this->userNotificationService->notify(
                recipient: $ride->user,
                type: 'ride.completed',
                title: 'Ride completed',
                body: "{$driver->name} marked the ride as completed.",
                data: ['ride_id' => $ride->id],
                sender: $driver
            );

            $this->notifyAdminsRideCompleted($ride);

            return $ride->load(['user', 'driver', 'conversation', 'histories']);
        });
    }

    public function expirePendingRides(): int
    {
        $rides = Ride::query()
            ->where('status', RideStatusEnum::PENDING->value)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now())
            ->with(['user', 'driver', 'conversation', 'histories'])
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

    private function assertCancelableStatus(Ride $ride): void
    {
        if ($ride->status->isTerminal()) {
            throw new HttpException(422, 'Ride is already finalized.');
        }

        if (! in_array($ride->status, [RideStatusEnum::PENDING, RideStatusEnum::ACTIVE, RideStatusEnum::ARRIVED], true)) {
            throw new HttpException(422, 'Ride cannot be cancelled in its current state.');
        }
    }

    private function notifyAdminsRideCompleted(Ride $ride): void
    {
        $this->userNotificationService->notifyUsersByRole(
            UserRole::ADMIN,
            'ride.completed_admin',
            'Ride completed',
            "Ride #{$ride->id} was marked completed.",
            [
                'ride_id' => $ride->id,
                'user_id' => $ride->user_id,
                'driver_id' => $ride->driver_id,
            ],
            null,
            null
        );
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
