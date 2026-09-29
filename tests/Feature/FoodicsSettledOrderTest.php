<?php

namespace Tests\Feature;

use App\Integrations\Foodics\Support\FoodicsStatusMapper;
use Tests\TestCase;

/**
 * Guards the fix for orders that stay active forever in the customer's app.
 *
 * Closing payment on the Foodics POS settles the check and stamps
 * `closed_at`, but leaves `status` on whatever preparation value it last
 * held. Both the webhook and the poll read `status` alone, so neither could
 * ever see the close — production had orders from June and July still
 * showing as in-progress in September, with `closed_at` set months earlier.
 *
 * Payloads below mirror real orders inspected on production.
 */
class FoodicsSettledOrderTest extends TestCase
{
    public function test_settled_check_completes_even_though_status_is_not_terminal(): void
    {
        // Park St. order: closed on the POS in July, status still 'preparing'.
        $order = [
            'status' => 4,
            'closed_at' => '2026-07-14 13:56:12',
            'business_date' => '2026-07-14',
            'payments' => [],
            'total_price' => 165,
        ];

        $this->assertSame('mixing', FoodicsStatusMapper::fromFoodics($order['status']),
            'the status field on its own still reads as in-progress');

        $this->assertSame('completed', FoodicsStatusMapper::fromFoodicsOrder($order),
            'the settled check must win over the stale status field');
    }

    public function test_open_check_keeps_its_preparation_status(): void
    {
        $order = [
            'status' => 4,
            'closed_at' => null,
            'payments' => [],
        ];

        $this->assertSame('mixing', FoodicsStatusMapper::fromFoodicsOrder($order),
            'an order still being prepared must not be completed early');
    }

    public function test_cancelled_order_stays_cancelled_even_when_closed(): void
    {
        // A cancelled order is also closed. Its own status is terminal and
        // more accurate than "completed", so it must not be overwritten.
        $order = [
            'status' => 7,
            'closed_at' => '2026-07-14 13:56:12',
        ];

        $this->assertSame('cancelled', FoodicsStatusMapper::fromFoodicsOrder($order));
    }

    public function test_settlement_is_recognised_from_any_of_the_three_signals(): void
    {
        $this->assertTrue(FoodicsStatusMapper::isSettled(['closed_at' => '2026-07-14 13:56:12']));
        $this->assertTrue(FoodicsStatusMapper::isSettled(['is_paid' => true]));
        $this->assertTrue(FoodicsStatusMapper::isSettled(['payments' => [['amount' => 165]]]));

        $this->assertFalse(FoodicsStatusMapper::isSettled([
            'closed_at' => null,
            'is_paid' => false,
            'payments' => [],
        ]));
        $this->assertFalse(FoodicsStatusMapper::isSettled([]));
    }

    public function test_payment_event_without_a_status_still_resolves(): void
    {
        // The webhook used to reject this with 422 as an incomplete payload.
        $order = ['closed_at' => '2026-07-14 13:56:12'];

        $this->assertNull(FoodicsStatusMapper::fromFoodics($order['status'] ?? null));
        $this->assertSame('completed', FoodicsStatusMapper::fromFoodicsOrder($order));
    }

    public function test_unmappable_status_on_an_open_check_is_still_unmappable(): void
    {
        $this->assertNull(FoodicsStatusMapper::fromFoodicsOrder(['status' => 99]));
    }
}
