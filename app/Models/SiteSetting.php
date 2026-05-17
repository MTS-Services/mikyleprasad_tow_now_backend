<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    public const PUBLIC_CACHE_KEY = 'site-settings:public:v2';

    protected $fillable = [
        'site_email',
        'site_phone',
        'site_address',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::clearPublicCache());
    }

    public static function clearPublicCache(): void
    {
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }
}
