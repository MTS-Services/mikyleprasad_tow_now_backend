<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

#[Fillable([
    'name',
    'username',
    'email',
    'phone',
    'locale',
    'timezone',
    'preferred_currency_id',
    'password',
    'role',
    'email_verified_at',
    'phone_verified_at',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements CanResetPasswordContract, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use CanResetPassword, HasApiTokens, HasFactory, Notifiable;

    use TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if ($user->username === null || $user->username === '') {
                $user->username = generate_username_hybrid();
            }

            if ($user->locale === null || $user->locale === '') {
                $user->locale = 'en';
            }

            if ($user->preferred_currency_id === null) {
                $user->preferred_currency_id = static::defaultPreferredCurrencyId();
            }
        });
    }

    protected static function defaultPreferredCurrencyId(): ?int
    {
        try {
            return Currency::query()->where('code', 'USD')->value('id');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function preferredCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'preferred_currency_id');
    }

    /**
     * @return HasMany<UserNotification, $this>
     */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'user_id', 'id')->orderByDesc('created_at');
    }

    /**
     * @return HasMany<UserNotification, $this>
     */
    public function sentUserNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'sender_id', 'id')->orderByDesc('created_at');
    }

    /**
     * @return HasMany<UserLoginHistory, $this>
     */
    public function loginHistories(): HasMany
    {
        return $this->hasMany(UserLoginHistory::class, 'user_id', 'id')->orderByDesc('id');
    }

    /**
     * @return HasOne<DriverProfile, $this>
     */
    public function driverProfile(): HasOne
    {
        return $this->hasOne(DriverProfile::class, 'user_id', 'id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'user_id', 'id');
    }
}
