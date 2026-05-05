<?php

namespace App\Enums;

enum UserRole: string
{
    case USER = 'user';
    case DRIVER = 'driver';
    case ADMIN = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::USER => 'User',
            self::DRIVER => 'Driver',
            self::ADMIN => 'Admin',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function isUser(): bool
    {
        return $this === self::USER;
    }

    public function isDriver(): bool
    {
        return $this === self::DRIVER;
    }
}
