<?php

namespace App\Enums;

enum OtpDeliveryPreference: string
{
    case Email = 'email';
    case Phone = 'phone';
    case UserChoice = 'user_choice';
}
