<?php

namespace App\Enums;

enum RideStatusEnum: string
{
    case REQUESTED = 'requested';
    case ACCEPTED = 'accepted';
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
}
