<?php

namespace App\Support;

/**
 * Money — single source of truth for EGP ⇄ piaster conversion.
 *
 * Every piaster amount in the codebase MUST go through here. `(int) ($x * 100)`
 * silently truncates IEEE-754 float artifacts: 1.15 → 114, 4.35 → 434, 19.99
 * → 1998. With MPGS receiving the truncated value and `refunded_amount`
 * comparing against the same truncated total, the wallet is short by a
 * piaster on average and `refund_status` flips to "full" before the gateway
 * is actually whole.
 */
final class Money
{
    /**
     * Convert a decimal EGP amount to piasters with banker-safe rounding.
     */
    public static function toPiasters(float|int|string $egp): int
    {
        return (int) round(((float) $egp) * 100);
    }

    /**
     * Format a piaster integer as a 2-decimal EGP string (e.g. "19.99").
     */
    public static function piastersToDecimalString(int $piasters): string
    {
        return number_format($piasters / 100, 2, '.', '');
    }
}
