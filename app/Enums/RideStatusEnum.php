<?php

namespace App\Enums;

enum RideStatusEnum: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case ARRIVED = 'arrived';
    case PICKED_UP = 'picked_up';
    case COMPLETED_USER = 'completed_user';
    case COMPLETED_DRIVER_PENDING_USER = 'completed_driver_pending_user';
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
            self::PICKED_UP => 'Picked Up',
            self::COMPLETED_USER => 'Completed (User)',
            self::COMPLETED_DRIVER_PENDING_USER => 'Completed (Driver Pending User)',
            self::CANCELLED_BY_USER => 'Cancelled (User)',
            self::CANCELLED_BY_DRIVER => 'Cancelled (Driver)',
            self::SYSTEM_CANCELLED => 'System Cancelled',
            self::EXPIRED => 'Expired',
            default => 'Unknown',
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

    public function isPickedUp(): bool
    {
        return $this === self::PICKED_UP;
    }

    public function isCompletedUser(): bool
    {
        return $this === self::COMPLETED_USER;
    }

    public function isCompletedDriverPendingUser(): bool
    {
        return $this === self::COMPLETED_DRIVER_PENDING_USER;
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
}
