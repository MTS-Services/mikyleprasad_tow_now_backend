<?php

namespace App\Enums;

enum LoginIdentifierType: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Username = 'username';
}
