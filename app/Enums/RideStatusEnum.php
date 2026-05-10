<?php

namespace App\Enums;

enum RideStatusEnum: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case ARRIVED = 'arrived';
    case COMPLETED_USER = 'completed_user';
    case CANCELLED_BY_USER = 'cancelled_by_user';
    case CANCELLED_BY_DRIVER = 'cancelled_by_driver';
    case SYSTEM_CANCELLED = 'system_cancelled';
    case EXPIRED = 'expired';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED_USER,
            self::CANCELLED_BY_USER,
            self::CANCELLED_BY_DRIVER,
            self::SYSTEM_CANCELLED,
            self::EXPIRED,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::ARRIVED => 'Arrived',
            self::COMPLETED_USER => 'Completed (User)',
            self::CANCELLED_BY_USER => 'Cancelled (User)',
            self::CANCELLED_BY_DRIVER => 'Cancelled (Driver)',
            self::SYSTEM_CANCELLED => 'System Cancelled',
            self::EXPIRED => 'Expired',
        };
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED_USER;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED_BY_USER || $this === self::CANCELLED_BY_DRIVER || $this === self::SYSTEM_CANCELLED;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isArrived(): bool
    {
        return $this === self::ARRIVED;
    }

    public function isCompletedUser(): bool
    {
        return $this === self::COMPLETED_USER;
    }

    public function isCancelledByUser(): bool
    {
        return $this === self::CANCELLED_BY_USER;
    }

    public function isCancelledByDriver(): bool
    {
        return $this === self::CANCELLED_BY_DRIVER;
    }

    public function isSystemCancelled(): bool
    {
        return $this === self::SYSTEM_CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this === self::EXPIRED;
    }

    /**
     * @return array<int, string>
     */
    public static function inProgressRideStatuses(): array
    {
        return [
            self::ACTIVE->value,
            self::ARRIVED->value,
        ];
    }
}
