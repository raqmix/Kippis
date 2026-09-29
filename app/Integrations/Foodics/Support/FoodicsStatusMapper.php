<?php

namespace App\Integrations\Foodics\Support;

/**
 * Translates Foodics v5 order status (integer enum) → Kippis order status
 * (string enum on `orders.status`). Centralised so the webhook and the poll
 * fallback emit the same updates.
 *
 * Foodics v5 status values (per documented enum):
 *   1 = pending, 2 = approved, 3 = on_hold, 4 = preparing,
 *   5 = ready,   6 = completed, 7 = cancelled
 *
 * Kippis order status enum: received | mixing | ready | completed | cancelled
 * (pending_payment is internal to the Kippis cash flow; never assigned from
 * Foodics).
 */
class FoodicsStatusMapper
{
    public static function fromFoodics(int|string|null $foodicsStatus): ?string
    {
        if ($foodicsStatus === null) {
            return null;
        }

        $code = (int) $foodicsStatus;

        return match ($code) {
            1, 2, 3 => 'received',
            4       => 'mixing',
            5       => 'ready',
            6       => 'completed',
            7       => 'cancelled',
            default => null,
        };
    }

    /**
     * Resolve a Kippis status from a whole Foodics order, not just its
     * `status` field.
     *
     * Closing payment on the POS settles the check — Foodics stamps
     * `closed_at` — but leaves `status` on whatever preparation value it
     * last held. Reading `status` alone therefore never sees the close, and
     * the order stays active in the customer's app indefinitely; we found
     * orders from June and July still showing as in-progress in September.
     *
     * A settled check is terminal, so `closed_at` wins over `status`. The
     * one exception is a cancellation: a cancelled order is also closed, and
     * its own status is already terminal and more accurate, so we keep it.
     *
     * @param  array<string, mixed>  $order  A Foodics order resource.
     */
    public static function fromFoodicsOrder(array $order): ?string
    {
        $fromStatus = self::fromFoodics($order['status'] ?? null);

        if ($fromStatus === 'cancelled') {
            return $fromStatus;
        }

        if (self::isSettled($order)) {
            return 'completed';
        }

        return $fromStatus;
    }

    /**
     * Whether Foodics considers this check settled and done with.
     *
     * `closed_at` is the field the POS stamps when staff close payment.
     * `is_paid` and a non-empty `payments` array are accepted as secondary
     * signals so this keeps working if a branch or API version reports the
     * settlement differently.
     *
     * @param  array<string, mixed>  $order
     */
    public static function isSettled(array $order): bool
    {
        if (! empty($order['closed_at'])) {
            return true;
        }

        if (! empty($order['is_paid'])) {
            return true;
        }

        return ! empty($order['payments']) && is_array($order['payments']);
    }

    /**
     * Terminal Kippis statuses — no further status updates expected from
     * Foodics. Used by the poll fallback to skip rows that won't change.
     */
    public static function terminalStatuses(): array
    {
        return ['completed', 'cancelled'];
    }
}
