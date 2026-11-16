<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Which client surface a request (and therefore a cart / order) came
 * from. Drives app-only perks such as the app discount.
 *
 * Resolution order (see resolve()):
 *   1. Kiosk routes (/api/v1/kiosk/*)                 => kiosk
 *   2. X-Kippis-Platform header: ios|android|mobile   => mobile, web => web
 *   3. User-Agent starting with "Dart/" (Flutter's
 *      default HTTP client, i.e. older app builds)    => mobile
 *   4. anything else                                  => unknown
 *
 * Orders created outside a request also carry a platform: squad orders
 * are `squad`, admin-built counter orders `counter`.
 *
 * ResolveClientPlatform middleware stores the resolved value on the
 * request attributes; current() reads it back.
 */
final class ClientPlatform
{
    public const MOBILE  = 'mobile';
    public const WEB     = 'web';
    public const KIOSK   = 'kiosk';
    public const SQUAD   = 'squad';
    public const COUNTER = 'counter';
    public const UNKNOWN = 'unknown';

    public const HEADER = 'X-Kippis-Platform';
    public const ATTRIBUTE = 'client_platform';

    public static function resolve(Request $request): string
    {
        if ($request->is('api/v1/kiosk', 'api/v1/kiosk/*')) {
            return self::KIOSK;
        }

        $header = strtolower(trim((string) $request->header(self::HEADER, '')));
        if (in_array($header, ['ios', 'android', 'mobile'], true)) {
            return self::MOBILE;
        }
        if ($header === 'web') {
            return self::WEB;
        }

        $ua = (string) $request->userAgent();
        if (str_starts_with($ua, 'Dart/')) {
            return self::MOBILE;
        }

        return self::UNKNOWN;
    }

    /**
     * The platform of the current HTTP request, or null when not in a
     * request that went through the middleware (console, queue, tests
     * that call models directly).
     */
    public static function current(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }
        $value = request()->attributes->get(self::ATTRIBUTE);

        return is_string($value) ? $value : null;
    }

    /** True when $platform is a known surface (not null / unknown). */
    public static function isKnown(?string $platform): bool
    {
        return $platform !== null && $platform !== '' && $platform !== self::UNKNOWN;
    }
}
