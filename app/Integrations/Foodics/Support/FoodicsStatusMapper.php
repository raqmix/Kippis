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
     * Terminal Kippis statuses — no further status updates expected from
     * Foodics. Used by the poll fallback to skip rows that won't change.
     */
    public static function terminalStatuses(): array
    {
        return ['completed', 'cancelled'];
    }
}
