<?php

namespace App\Enums;

enum RideHistoryTypeEnum: string
{
    case STATUS = 'status';
    case ESTIMATED_TIME = 'estimated_time';
    case COMPLETE = 'complete';
}
