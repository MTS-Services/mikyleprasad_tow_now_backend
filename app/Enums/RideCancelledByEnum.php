<?php

namespace App\Enums;

enum RideCancelledByEnum: string
{
    case USER = 'user';
    case DRIVER = 'driver';
    case SYSTEM = 'system';
}
