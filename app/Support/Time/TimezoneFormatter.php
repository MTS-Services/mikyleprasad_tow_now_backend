<?php

namespace App\Support\Time;

use App\Services\Time\RequestTimezoneResolver;
use Carbon\CarbonInterface;

final class TimezoneFormatter
{
    public static function format(?CarbonInterface $value, string $format = 'Y-m-d H:i:s'): ?string
    {
        if ($value === null) {
            return null;
        }

        $tz = self::resolvedTimezone();

        $asUtc = $value->avoidMutation()->shiftTimezone('UTC');

        return $asUtc->setTimezone($tz)->format($format);
    }

    public static function iso(?CarbonInterface $value): ?string
    {
        return self::format($value);
    }

    private static function resolvedTimezone(): string
    {
        try {
            return app(RequestTimezoneResolver::class)->resolve();
        } catch (\Throwable) {
            return (string) config('app.timezone', 'UTC');
        }
    }
}
