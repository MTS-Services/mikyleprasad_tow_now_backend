<?php

namespace App\Enums;

enum LoginType: string
{
    case Password = 'password';
    case Otp = 'otp';
}
