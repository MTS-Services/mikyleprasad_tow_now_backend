<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
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
    'status',
    'approval_status',
    'address',
    'bio',
    'is_suspended',
    'is_featured',
    'email_verified_at',
    'phone_verified_at',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'two_factor_confirmed_at',
    'remember_token',
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
            'status' => AccountStatus::class,
            'approval_status' => ApprovalStatus::class,
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
     * @return HasOne<Vehicle, $this>
     */
    public function vehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class, 'user_id', 'id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'user_id', 'id');
    }

    /**
     * @return HasMany<Ride, $this>
     */
    public function requestedRides(): HasMany
    {
        return $this->hasMany(Ride::class, 'user_id', 'id')->orderByDesc('id');
    }

    /**
     * @return HasMany<Ride, $this>
     */
    public function assignedRides(): HasMany
    {
        return $this->hasMany(Ride::class, 'driver_id', 'id')->orderByDesc('id');
    }

    /**
     * Get total rides count for this user (as driver)
     */
    public function getTotalRidesAttribute(): int
    {
        return $this->assignedRides()->count();
    }

    /**
     * Get completed rides count for this user (as driver)
     */
    public function getCompletedRidesAttribute(): int
    {
        return $this->assignedRides()
            ->where('status', \App\Enums\RideStatusEnum::COMPLETED_USER->value)
            ->count();
    }

    /**
     * Get cancelled rides count for this user (as driver)
     */
    public function getCancelledRidesAttribute(): int
    {
        return $this->assignedRides()
            ->whereIn('status', [
                \App\Enums\RideStatusEnum::CANCELLED_BY_USER->value,
                \App\Enums\RideStatusEnum::CANCELLED_BY_DRIVER->value,
                \App\Enums\RideStatusEnum::SYSTEM_CANCELLED->value,
                \App\Enums\RideStatusEnum::EXPIRED->value,
            ])
            ->count();
    }

    /**
     * Get active rides count for this user (as driver)
     */
    public function getActiveRidesAttribute(): int
    {
        return $this->assignedRides()
            ->whereIn('status', [
                \App\Enums\RideStatusEnum::PENDING->value,
                \App\Enums\RideStatusEnum::ACTIVE->value,
                \App\Enums\RideStatusEnum::ARRIVED->value,
                \App\Enums\RideStatusEnum::PICKED_UP->value,
                \App\Enums\RideStatusEnum::COMPLETED_DRIVER_PENDING_USER->value,
            ])
            ->count();
    }

    /**
     * Get all ride statistics for this user (as driver)
     */
    public function getRideStatisticsAttribute(): array
    {
        return [
            'total_rides' => $this->total_rides,
            'completed_rides' => $this->completed_rides,
            'cancelled_rides' => $this->cancelled_rides,
            'active_rides' => $this->active_rides,
        ];
    }
}
