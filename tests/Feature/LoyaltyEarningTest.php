<?php

namespace Tests\Feature;

use App\Core\Models\Customer;
use App\Core\Models\Order;
use App\Core\Models\PointsMultiplierRule;
use App\Core\Models\Setting;
use App\Events\MonthlyDrawEntered;
use App\Services\Loyalty\PointsEarning;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** Brief section C: earning rate and double-points rules. */
class LoyaltyEarningTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::factory()->create();
    }

    /** Create an order and complete it. $cairo is the Cairo wall-clock time it was placed. */
    private function complete(float $total, string $cairo = '2026-11-18 12:00', string $platform = 'mobile', ?Customer $customer = null): Order
    {
        $order = Order::factory()->create([
            'customer_id' => ($customer ?? $this->customer)->id,
            'status'      => 'received',
            'total'       => $total,
            'platform'    => $platform,
            'created_at'  => Carbon::parse($cairo, 'Africa/Cairo')->utc(),
            'updated_at'  => Carbon::parse($cairo, 'Africa/Cairo')->utc(),
        ]);
        $order->update(['status' => 'completed']);

        return $order;
    }

    private function points(): int
    {
        return (int) ($this->customer->loyaltyWallet()->first()?->points ?? 0);
    }

    private function lastDescription(): string
    {
        return (string) $this->customer->loyaltyWallet->transactions()->latest('id')->value('description');
    }

    public function test_egp_per_point_floors_when_set(): void
    {
        Setting::set('loyalty.egp_per_point', 10, 'number', 'loyalty');
        $this->complete(129.90);

        $this->assertSame(12, $this->points());
    }

    public function test_old_formula_when_egp_per_point_is_null(): void
    {
        Setting::set('loyalty.points_per_order_egp', 2, 'number', 'loyalty');
        $this->complete(100.40);

        $this->assertSame(201, $this->points()); // round(100.40 * 2)
    }

    public function test_egp_per_point_zero_falls_back_to_old_formula(): void
    {
        Setting::set('loyalty.egp_per_point', 0, 'number', 'loyalty');
        Setting::set('loyalty.points_per_order_egp', 1, 'number', 'loyalty');
        $this->complete(50);

        $this->assertSame(50, $this->points());
    }

    public function test_monday_rule_uses_cairo_day_not_utc(): void
    {
        Setting::set('loyalty.egp_per_point', 10, 'number', 'loyalty');
        PointsMultiplierRule::create(['name' => 'Monday Refill', 'multiplier' => 2, 'days_of_week' => [1]]);

        // Mon 16 Nov 2026 01:30 Cairo = Sun 23:30 UTC: still Monday in Cairo.
        $this->complete(100, '2026-11-16 01:30');
        $this->assertSame(20, $this->points());
        $this->assertStringContainsString('x2 Monday Refill', $this->lastDescription());

        // Mon 23:30 Cairo = Mon 21:30 UTC: Monday.
        $this->complete(100, '2026-11-16 23:30');
        $this->assertSame(40, $this->points());

        // Tue 00:30 Cairo = Mon 22:30 UTC: not Monday any more.
        $this->complete(100, '2026-11-17 00:30');
        $this->assertSame(50, $this->points());
    }

    public function test_time_window_in_cairo_time(): void
    {
        Setting::set('loyalty.egp_per_point', 10, 'number', 'loyalty');
        PointsMultiplierRule::create(['name' => 'Happy Hour', 'multiplier' => 2, 'time_from' => '15:00', 'time_to' => '18:00']);

        $this->complete(100, '2026-11-18 14:59');
        $this->assertSame(10, $this->points());
        $this->complete(100, '2026-11-18 15:00');
        $this->assertSame(30, $this->points());
        $this->complete(100, '2026-11-18 17:59');
        $this->assertSame(50, $this->points());
        $this->complete(100, '2026-11-18 18:00'); // end is exclusive
        $this->assertSame(60, $this->points());
    }

    public function test_highest_multiplier_wins_and_rules_do_not_stack(): void
    {
        Setting::set('loyalty.egp_per_point', 10, 'number', 'loyalty');
        PointsMultiplierRule::create(['name' => 'Happy Hour', 'multiplier' => 2, 'time_from' => '15:00', 'time_to' => '18:00']);
        PointsMultiplierRule::create(['name' => 'Mega', 'multiplier' => 3, 'days_of_week' => [3]]); // Wed 18 Nov
        PointsMultiplierRule::create(['name' => 'Off', 'multiplier' => 10, 'is_active' => false]);

        $this->complete(100, '2026-11-18 16:00');

        $this->assertSame(30, $this->points());
        $this->assertStringContainsString('x3 Mega', $this->lastDescription());
    }

    public function test_date_range_and_platform_filters(): void
    {
        Setting::set('loyalty.egp_per_point', 10, 'number', 'loyalty');
        PointsMultiplierRule::create([
            'name' => 'Week', 'multiplier' => 2, 'starts_at' => '2026-11-18', 'ends_at' => '2026-11-18',
            'platforms' => ['web'],
        ]);

        // Right day but platform mobile is not in the rule's platforms.
        $this->complete(100, '2026-11-18 12:00');
        $this->assertSame(10, $this->points());

        PointsMultiplierRule::query()->update(['platforms' => null]);
        $this->complete(100, '2026-11-18 12:00');
        $this->assertSame(30, $this->points());
        $this->complete(100, '2026-11-19 12:00'); // day after the range
        $this->assertSame(40, $this->points());
    }

    public function test_non_app_orders_get_no_multiplier_and_no_draw_entry(): void
    {
        Setting::set('loyalty.egp_per_point', 10, 'number', 'loyalty');
        PointsMultiplierRule::create(['name' => 'Always', 'multiplier' => 2]);
        Event::fake([MonthlyDrawEntered::class]);

        foreach (['web', 'kiosk', 'counter', 'squad', 'unknown'] as $platform) {
            $this->complete(100, '2026-11-18 12:00', $platform);
        }
        $this->assertSame(50, $this->points());
        Event::assertNotDispatched(MonthlyDrawEntered::class);

        $order = $this->complete(100, '2026-11-18 12:00', 'mobile');
        $this->assertSame(70, $this->points());
        Event::assertDispatched(MonthlyDrawEntered::class, fn ($e) => $e->order->is($order) && $e->customer->is($this->customer));
    }

    public function test_app_platforms_setting_decides_what_is_an_app_order(): void
    {
        Setting::set('loyalty.egp_per_point', 10, 'number', 'loyalty');
        Setting::set('app_offer.platforms', ['mobile', 'web'], 'json', 'app_offer');
        PointsMultiplierRule::create(['name' => 'Always', 'multiplier' => 2]);

        $order = $this->complete(100, '2026-11-18 12:00', 'web');

        $this->assertSame(20, app(PointsEarning::class)->calculate($order)['points']);
        $this->assertSame(20, $this->points());
    }

    public function test_completing_twice_does_not_double_award(): void
    {
        Setting::set('loyalty.egp_per_point', 10, 'number', 'loyalty');
        $order = $this->complete(100);
        $order->update(['status' => 'completed', 'tax' => 1]);

        $this->assertSame(10, $this->points());
    }
}
