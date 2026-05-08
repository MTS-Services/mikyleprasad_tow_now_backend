<?php

namespace App\Enums;

enum RideStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case CANCELED = 'canceled';
    case REJECTED = 'rejected';
    case COMPLETED = 'completed';
    case SYSTEM_CANCELLED = 'system_cancelled';
}
