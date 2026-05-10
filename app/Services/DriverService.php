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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DriverService
{
    public function __construct(
        private readonly DriverQueryFilters $driverQueryFilters
    ) {}

    /**
     * @param  array{q?: ?string, status?: ?string, featured?: string, per_page?: int}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', UserRole::DRIVER->value)
            ->where('approval_status', ApprovalStatus::APPROVED->value)
            ->where('is_suspended', false)
            // ->with('vehicle')
            ->orderByDesc('id');

        $this->driverQueryFilters->apply($query, $filters);

        $featured = filter_var((string) ($filters['featured'] ?? ''), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($featured === true) {
            $query->where('is_featured', true);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage)->withQueryString();
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

    public function updateDriverProfile(array $data): ?User
    {
        Validator::make($data, [
            'name'    => ['sometimes', 'string', 'max:255'],
            'phone'   => ['sometimes', 'string', 'max:20'],
            'address' => ['sometimes', 'string', 'max:500'],
            'avatar'  => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ])->validate();

        $driver = $this->getDriverProfile();

        if (! $driver) {
            return null;
        }

        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $this->deleteAvatarFile($driver->avatar);
            $data['avatar'] = $this->storeAvatar($data['avatar'], $driver->id);
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
}
