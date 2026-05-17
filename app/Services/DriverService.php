<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\RideStatusEnum;
use App\Enums\UserRole;
use App\Models\Ride;
use App\Models\User;
use App\Support\Filters\DriverQueryFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DriverService
{
    public function __construct(
        private readonly DriverQueryFilters $driverQueryFilters
    ) {}

    /**
     * @param  array{
     *     audience?: string,
     *     tab?: string,
     *     q?: ?string,
     *     status?: ?string,
     *     sort?: ?string,
     *     seed?: ?string,
     *     per_page?: int,
     *     page?: int
     * }  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $audience = (string) ($filters['audience'] ?? 'admin');
        $tab = (string) ($filters['tab'] ?? ($audience === 'public' ? 'all' : 'pending'));
        $sort = (string) ($filters['sort'] ?? ($audience === 'public' ? 'random' : 'latest'));

        $query = User::query()
            ->select([
                'id',
                'name',
                'username',
                'avatar',
                'email',
                'phone',
                'address',
                'approval_status',
                'status',
                'is_suspended',
                'is_featured',
                'role',
                'created_at',
            ])
            ->where('role', UserRole::DRIVER->value)
            ->with([
                'vehicle:id,user_id,name,brand,model,capacity,license_plate,insurance_status',
            ]);

        $this->applyAudienceTab($query, $audience, $tab);
        $this->driverQueryFilters->apply($query, $filters);
        $this->applySort($query, $audience, $sort, isset($filters['seed']) ? (string) $filters['seed'] : null);

        return $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    private function applyAudienceTab(Builder $query, string $audience, string $tab): void
    {
        if ($audience === 'public') {
            $query
                ->where('approval_status', ApprovalStatus::APPROVED->value)
                ->where('is_suspended', false);

            if ($tab === 'featured_drivers') {
                $query->where('is_featured', true);
            }

            return;
        }

        match ($tab) {
            'pending' => $query
                ->where('approval_status', ApprovalStatus::PENDING->value)
                ->where('is_suspended', false),
            'all' => $query
                ->where('approval_status', ApprovalStatus::APPROVED->value),
                // ->where('is_suspended', false),
            'featured_drivers' => $query
                ->where('approval_status', ApprovalStatus::APPROVED->value)
                ->where('is_suspended', false)
                ->where('is_featured', true),
            'suspended' => $query->where('is_suspended', true),
            'rejected' => $query
                ->where('approval_status', ApprovalStatus::REJECTED->value)
                ->where('is_suspended', false),
            default => $query
                ->where('approval_status', ApprovalStatus::PENDING->value)
                ->where('is_suspended', false),
        };
    }

    private function applySort(Builder $query, string $audience, string $sort, ?string $seed): void
    {
        if ($audience === 'public') {
            if ($sort === 'random') {
                $this->applySeededRandomOrder($query, $seed);

                return;
            }

            if ($sort === 'oldest') {
                $query->orderBy('created_at')->orderBy('id');

                return;
            }

            $query->orderByDesc('created_at')->orderByDesc('id');

            return;
        }

        if ($sort === 'oldest') {
            $query->orderBy('created_at')->orderBy('id');

            return;
        }

        $query->orderByDesc('created_at')->orderByDesc('id');
    }

    private function applySeededRandomOrder(Builder $query, ?string $seed): void
    {
        $driver = $query->getConnection()->getDriverName();

        if ($seed !== null && $seed !== '') {
            $seedInt = crc32($seed);

            if ($driver === 'sqlite') {
                $query->orderByRaw('((id * ?) % 2147483647)', [$seedInt]);
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $query->orderByRaw('RAND(?)', [$seedInt]);
            } else {
                $query->orderByRaw('md5(concat(id, ?))', [$seed]);
            }

            return;
        }

        if ($driver === 'sqlite') {
            $query->orderByRaw('random()');
        } else {
            $query->inRandomOrder();
        }
    }

    public function find(int $id): ?User
    {
        return User::query()
            ->whereKey($id)
            ->where('role', UserRole::DRIVER->value)
            ->with(['vehicle', 'preferredCurrency', 'assignedRides'])
            ->first();
    }

    /**
     * Get total rides count for a driver
     */
    public function getTotalRides(int $driverId): int
    {
        return Ride::query()
            ->where('driver_id', $driverId)
            ->count();
    }

    /**
     * Get completed rides count for a driver
     */
    public function getCompletedRides(int $driverId): int
    {
        return Ride::query()
            ->where('driver_id', $driverId)
            ->where('status', RideStatusEnum::COMPLETED_USER->value)
            ->count();
    }

    /**
     * Get cancelled rides count for a driver
     */
    public function getCancelledRides(int $driverId): int
    {
        return Ride::query()
            ->where('driver_id', $driverId)
            ->whereIn('status', [
                RideStatusEnum::CANCELLED_BY_USER->value,
                RideStatusEnum::CANCELLED_BY_DRIVER->value,
                RideStatusEnum::SYSTEM_CANCELLED->value,
                RideStatusEnum::EXPIRED->value,
            ])
            ->count();
    }

    /**
     * Get active rides count for a driver
     */
    public function getActiveRides(int $driverId): int
    {
        return Ride::query()
            ->where('driver_id', $driverId)
            ->whereIn('status', [
                RideStatusEnum::PENDING->value,
                RideStatusEnum::ACTIVE->value,
                RideStatusEnum::ARRIVED->value,
            ])
            ->count();
    }

    /**
     * Get all ride statistics for a driver
     */
    public function getRideStatistics(int $driverId): array
    {
        return [
            'total_rides' => $this->getTotalRides($driverId),
            'completed_rides' => $this->getCompletedRides($driverId),
            'cancelled_rides' => $this->getCancelledRides($driverId),
            'active_rides' => $this->getActiveRides($driverId),
        ];
    }

    public function acceptDriver(int $driverId): void
    {
        $driver = User::query()->whereKey($driverId)->where('role', UserRole::DRIVER->value)->first();
        if ($driver) {
            $driver->update(['approval_status' => ApprovalStatus::APPROVED->value]);
        }
    }

    public function rejectDriver(int $driverId): void
    {
        $driver = User::query()->whereKey($driverId)->where('role', UserRole::DRIVER->value)->first();
        if ($driver) {
            $driver->update(['approval_status' => ApprovalStatus::REJECTED->value]);
        }
    }

    public function getDriverProfile(): ?User
    {
        $userId = auth()->id();

        return User::query()
            ->whereKey($userId)
            ->where('role', UserRole::DRIVER->value)
            ->with('vehicle')
            ->first();
    }

    public function updateDriverProfile(int $driverId, array $data): ?User
    {

        $driver = User::find($driverId);

        if (! $driver) {
            return null;
        }

        $driver->update($data);

        return $driver->fresh();
    }

    private function storeAvatar(UploadedFile $file, int|string $userId): string
    {
        $path = $file->store("avatars/{$userId}", 'public');

        return Storage::url($path);
    }

    private function deleteAvatarFile(?string $avatarUrl): void
    {
        if (! $avatarUrl) {
            return;
        }

        $path = ltrim(str_replace('/storage', '', $avatarUrl), '/');

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function suspendDriver(int $driverId): void
    {
        $driver = User::query()
            ->whereKey($driverId)
            ->where('role', UserRole::DRIVER->value)
            ->first();

        if ($driver) {
            $driver->update([
                'is_suspended' => true,
            ]);
        }
    }

    public function unsuspendDriver(int $driverId)
    {
        $driver = User::query()
            ->whereKey($driverId)
            ->where('role', UserRole::DRIVER->value)
            ->first();

        if ($driver) {
            $driver->update([
                'is_suspended' => false,
            ]);
        }
    }

    public function featuredDriver(int $driverId)
    {
        $driver = User::query()
            ->whereKey($driverId)
            ->where('role', UserRole::DRIVER->value)
            ->first();

        if ($driver) {
            $driver->update([
                'is_featured' => true,
            ]);
        }
    }
    
    public function unfeaturedDriver(int $driverId)
    {
        $driver = User::query()
            ->whereKey($driverId)
            ->where('role', UserRole::DRIVER->value)
            ->first();

        if ($driver) {
            $driver->update([
                'is_featured' => false,
            ]);
        }
    }
}
