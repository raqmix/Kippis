<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Records and reads "X was alive at T" pings used by the system-health widget.
 * Cache-backed so it works across the worker / web / scheduler processes
 * without depending on filesystem permissions.
 */
class Heartbeat
{
    public static function mark(string $channel): void
    {
        Cache::forever(self::key($channel), now()->toIso8601String());
    }

    public static function lastSeen(string $channel): ?CarbonImmutable
    {
        $value = Cache::get(self::key($channel));
        return $value ? CarbonImmutable::parse($value) : null;
    }

    public static function ageSeconds(string $channel): ?int
    {
        $last = self::lastSeen($channel);
        return $last ? (int) $last->diffInSeconds(now()) : null;
    }

    public static function isHealthy(string $channel, int $maxAgeSeconds): bool
    {
        $age = self::ageSeconds($channel);
        return $age !== null && $age <= $maxAgeSeconds;
    }

    private static function key(string $channel): string
    {
        return "heartbeat:{$channel}";
    }
}
