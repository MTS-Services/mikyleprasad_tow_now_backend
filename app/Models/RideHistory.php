<?php

namespace App\Models;

use App\Enums\RideHistoryTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ride_id',
    'user_id',
    'type',
    'from_status',
    'to_status',
    'time',
    'reason',
    'data',
])]
class RideHistory extends Model
{
    /**
     * @return BelongsTo<Ride, $this>
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class, 'ride_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'type' => RideHistoryTypeEnum::class,
            'data' => 'array',
        ];
    }
}
