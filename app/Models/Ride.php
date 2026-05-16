<?php

namespace App\Models;

use App\Enums\RideCancelledByEnum;
use App\Enums\RideStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'uuid',
    'user_id',
    'driver_id',
    'status',
    'pickup_location',
    'dropoff_location',
    'notes',
    'eta_minutes',
    'eta_reason',
    'cancel_reason',
    'cancelled_by',
    'total_arrival_time',
    'total_ride_time',
    'total_arrival_minutes',
    'total_ride_minutes',
    'expired_at',
    'accepted_at',
    'arrived_at',
    'picked_up_at',
    'completion_requested_at',
    'completed_at',
    'cancelled_at',
    'pickup_lat',
    'pickup_lng',
    'dropoff_lat',
    'dropoff_lng',
    'offline_temp_id',
    'synced_from_offline',
    'problem_type',
    'problem_description',
    'estimated_price',
    'final_price',
    'payment_status',
])]

class Ride extends Model
{
    protected $casts = [
        'expired_at' => 'datetime',
        'accepted_at' => 'datetime',
        'arrived_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'completion_requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancelled_by' => RideCancelledByEnum::class,
        'status' => RideStatusEnum::class,
        'pickup_lat' => 'float',
        'pickup_lng' => 'float',
        'dropoff_lat' => 'float',
        'dropoff_lng' => 'float',
        'synced_from_offline' => 'bool',
        'estimated_price' => 'float',
        'final_price' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (Ride $ride): void {
            if ($ride->uuid === null || $ride->uuid === '') {
                $ride->uuid = generate_uuid();
            }
        });
    }

    /**
     * Resolve API route keys: numeric primary key, RFC UUID, or app ride `uuid` (e.g. UD-…).
     *
     * @param  Builder<Ride>  $query
     * @return Builder<Ride>
     */
    public function scopeWhereIdOrUuid(Builder $query, string $value): Builder
    {
        if ($value !== '' && ctype_digit($value)) {
            return $query->whereKey((int) $value);
        }

        return $query->where('uuid', $value);
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        return static::query()->whereIdOrUuid((string) $value)->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * @return HasOne<Conversation, $this>
     */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class, 'ride_id');
    }

    /**
     * @return HasMany<RideHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(RideHistory::class, 'ride_id')->orderByDesc('id');
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class, 'ride_id');
    }
}
