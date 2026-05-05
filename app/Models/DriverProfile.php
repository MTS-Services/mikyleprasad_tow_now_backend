<?php

namespace App\Models;

use Database\Factories\DriverProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'car_brand',
    'car_model',
    'car_type',
    'license_plate',
    'location',
    'truck_image_path',
    'driving_license_image_path',
    'car_legal_documents_path',
])]
class DriverProfile extends Model
{
    /** @use HasFactory<DriverProfileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
