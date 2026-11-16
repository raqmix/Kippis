<?php

namespace Tests\Feature;

use App\Core\Models\Customer;
use App\Core\Models\CustomerRedeemWallet;
use App\Core\Models\Order;
use App\Events\OrderStreakAlmostComplete;
use App\Events\OrderStreakRewardIssued;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** Brief section C: order streak. */
class OrderStreakTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function complete(string $cairo, string $platform = 'mobile'): Order
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status'      => 'received',
            'total'       => 100,
            'platform'    => $platform,
            'created_at'  => Carbon::parse($cairo, 'Africa/Cairo')->utc(),
            'updated_at'  => Carbon::parse($cairo, 'Africa/Cairo')->utc(),
        ]);
        $order->update(['status' => 'completed']);

        return $order;
    }

    private function streak(): array
    {
        $this->customer->refresh();

        return [(int) $this->customer->order_streak_count, $this->customer->order_streak_last_date?->toDateString()];
    }

    public function test_same_day_orders_count_once_and_consecutive_days_increment(): void
    {
        $this->complete('2026-11-10 09:00');
        $this->assertSame([1, '2026-11-10'], $this->streak());

        $this->complete('2026-11-10 18:00');
        $this->assertSame([1, '2026-11-10'], $this->streak());

        $this->complete('2026-11-11 10:00');
        $this->assertSame([2, '2026-11-11'], $this->streak());

        $this->complete('2026-11-12 10:00');
        $this->assertSame([3, '2026-11-12'], $this->streak());
    }

    public function test_cairo_day_boundary_is_used(): void
    {
        // 00:30 Cairo on the 11th is still the 10th in UTC.
        $this->complete('2026-11-10 23:30');
        $this->complete('2026-11-11 00:30');

        $this->assertSame([2, '2026-11-11'], $this->streak());
    }

    public function test_gap_resets_to_one(): void
    {
        $this->complete('2026-11-10 10:00');
        $this->complete('2026-11-11 10:00');
        $this->complete('2026-11-13 10:00'); // 12th skipped

        $this->assertSame([1, '2026-11-13'], $this->streak());
    }

    public function test_late_completion_of_older_order_changes_nothing(): void
    {
        $this->complete('2026-11-12 10:00');
        $this->complete('2026-11-10 10:00');

        $this->assertSame([1, '2026-11-12'], $this->streak());
    }

    public function test_almost_event_at_four_and_reward_with_reset_at_five(): void
    {
        Event::fake([OrderStreakAlmostComplete::class, OrderStreakRewardIssued::class]);

        foreach (['10', '11', '12'] as $day) {
            $this->complete("2026-11-{$day} 10:00");
        }
        Event::assertNotDispatched(OrderStreakAlmostComplete::class);

        $this->complete('2026-11-13 10:00');
        Event::assertDispatched(OrderStreakAlmostComplete::class, fn ($e) => $e->count === 4 && $e->length === 5 && $e->customer->is($this->customer));
        Event::assertNotDispatched(OrderStreakRewardIssued::class);

        $this->complete('2026-11-14 10:00');
        $this->assertSame([0, '2026-11-14'], $this->streak());

        $rewards = CustomerRedeemWallet::where('customer_id', $this->customer->id)->where('source', 'streak')->get();
        $this->assertCount(1, $rewards);
        $this->assertSame('free_item', $rewards[0]->reward_type);
        $this->assertSame('streak:2026-11-14', $rewards[0]->source_ref);
        Event::assertDispatched(OrderStreakRewardIssued::class, fn ($e) => $e->reward->is($rewards[0]));

        // Another order the same day neither re-counts nor re-issues.
        $this->complete('2026-11-14 20:00');
        $this->assertSame([0, '2026-11-14'], $this->streak());

        // New cycle starts the next day.
        $this->complete('2026-11-15 10:00');
        $this->assertSame([1, '2026-11-15'], $this->streak());
        $this->assertSame(1, CustomerRedeemWallet::where('source', 'streak')->count());
    }

    public function test_only_app_orders_count(): void
    {
        foreach (['web', 'kiosk', 'counter', 'squad', 'unknown'] as $platform) {
            $this->complete('2026-11-10 10:00', $platform);
        }

        $this->assertSame([0, null], $this->streak());
    }

    public function test_disabled_streak_and_custom_length(): void
    {
        \App\Core\Models\Setting::set('order_streak.enabled', false, 'boolean', 'order_streak');
        $this->complete('2026-11-10 10:00');
        $this->assertSame([0, null], $this->streak());

        \App\Core\Models\Setting::set('order_streak.enabled', true, 'boolean', 'order_streak');
        \App\Core\Models\Setting::set('order_streak.length', 2, 'number', 'order_streak');
        $this->complete('2026-11-10 10:00');
        $this->complete('2026-11-11 10:00');

        $this->assertSame([0, '2026-11-11'], $this->streak());
        $this->assertSame(1, CustomerRedeemWallet::where('source', 'streak')->count());
    }

    public function test_status_endpoint_shows_zero_after_a_missed_day(): void
    {
        $this->complete('2026-11-10 10:00');
        $this->complete('2026-11-11 10:00');
        $this->complete('2026-11-12 10:00');

        $this->customer->refresh();
        // Cairo today = 12th: ordered today.
        Carbon::setTestNow(Carbon::parse('2026-11-12 20:00', 'Africa/Cairo'));
        $this->actingAs($this->customer, 'api')->getJson('/api/v1/order-streak')
            ->assertOk()
            ->assertJsonPath('data', ['enabled' => true, 'count' => 3, 'length' => 5, 'remaining' => 2, 'ordered_today' => true, 'at_risk' => false]);

        // 13th: yesterday was the last order, streak alive but at risk.
        Carbon::setTestNow(Carbon::parse('2026-11-13 09:00', 'Africa/Cairo'));
        $this->actingAs($this->customer, 'api')->getJson('/api/v1/order-streak')
            ->assertJsonPath('data.count', 3)
            ->assertJsonPath('data.ordered_today', false)
            ->assertJsonPath('data.at_risk', true);

        // 14th: a full day missed, shown as 0.
        Carbon::setTestNow(Carbon::parse('2026-11-14 09:00', 'Africa/Cairo'));
        $this->actingAs($this->customer, 'api')->getJson('/api/v1/order-streak')
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.at_risk', false)
            ->assertJsonPath('data.remaining', 5);

        $this->getJson('/api/v1/app-config')->assertJsonPath('data.order_streak', ['enabled' => true, 'length' => 5]);
    }

    public function test_status_requires_auth(): void
    {
        $this->getJson('/api/v1/order-streak')->assertUnauthorized();
    }
}
