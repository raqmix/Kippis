<?php

namespace Tests\Feature;

use App\Core\Models\Admin;
use App\Core\Models\Customer;
use App\Core\Models\Order;
use App\Core\Models\Setting;
use App\Core\Models\Store;
use App\Filament\Pages\WeeklyReport;
use App\Services\Reports\WeeklyStoreReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Brief section F: weekly report. Week = Mon-Sun, Africa/Cairo. */
class WeeklyReportTest extends TestCase
{
    use RefreshDatabase;

    // 2026-11-09 is a Monday. Cairo is UTC+2 in November (no DST).
    private const WEEK = '2026-11-11';

    private Store $a;
    private Store $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Store::factory()->create(['name' => 'Park St.']);
        $this->b = Store::factory()->create(['name' => 'The Yard']);
    }

    /** Cairo wall-clock time -> UTC Carbon. */
    private function cairo(string $when): Carbon
    {
        return Carbon::parse($when, 'Africa/Cairo')->utc();
    }

    private function order(Store $store, ?Customer $customer, string $when, float $total, string $platform = 'mobile', string $status = 'completed'): Order
    {
        $order = Order::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer?->id,
            'status' => $status,
            'total' => $total,
            'platform' => $platform,
            'created_at' => $this->cairo($when),
            'updated_at' => $this->cairo($when),
        ]);

        return $order;
    }

    private function signup(string $when): Customer
    {
        return Customer::factory()->create(['created_at' => $this->cairo($when)]);
    }

    private function row(array $report, string|int $label): array
    {
        return collect($report['rows'])->firstWhere('label', $label);
    }

    public function test_week_start_snaps_to_cairo_monday(): void
    {
        $this->assertSame('2026-11-09', WeeklyStoreReport::weekStart('2026-11-15')->toDateString());
        $this->assertSame('2026-11-09', WeeklyStoreReport::weekStart('2026-11-09')->toDateString());
        $this->assertSame('2026-11-16', WeeklyStoreReport::weekStart('2026-11-16')->toDateString());
    }

    public function test_figures_per_store(): void
    {
        $c1 = $this->signup('2026-11-10 10:00'); // first order at store A, in week
        $c2 = $this->signup('2026-11-12 10:00'); // first order at store B
        $c3 = $this->signup('2026-11-13 10:00'); // no order yet
        $old = Customer::factory()->create(['created_at' => $this->cairo('2026-10-01 10:00')]);

        $this->order($this->a, $c1, '2026-11-10 12:00', 100, 'mobile');
        $this->order($this->a, $c1, '2026-11-11 12:00', 50, 'web');       // not a first order
        $this->order($this->b, $c2, '2026-11-12 12:00', 80, 'mobile');
        $this->order($this->a, $old, '2026-10-02 12:00', 40, 'web');      // before the week
        $this->order($this->b, $old, '2026-11-12 13:00', 60, 'web');      // returning customer
        $this->order($this->b, $c3, '2026-11-12 14:00', 999, 'mobile', 'cancelled'); // cancelled: ignored

        $report = app(WeeklyStoreReport::class)->build(self::WEEK);

        $this->assertSame('2026-11-09', $report['week_start']);
        $this->assertSame('2026-11-15', $report['week_end']);

        $a = $this->row($report, 'Park St.');
        $this->assertSame(1, $a['signups']);
        $this->assertSame(1, $a['first_orders']);
        $this->assertSame(1, $a['app_orders']);
        $this->assertEquals(100.0, $a['app_revenue']);
        $this->assertSame(2, $a['all_orders']);
        $this->assertEquals(150.0, $a['all_revenue']);

        $b = $this->row($report, 'The Yard');
        $this->assertSame(1, $b['signups']);
        $this->assertSame(1, $b['first_orders']);
        $this->assertSame(1, $b['app_orders']);
        $this->assertEquals(80.0, $b['app_revenue']);
        $this->assertSame(2, $b['all_orders']);
        $this->assertEquals(140.0, $b['all_revenue']);

        $none = $this->row($report, 'No order yet');
        $this->assertSame(1, $none['signups']);
        $this->assertSame(0, $none['all_orders']);

        $t = $report['totals'];
        $this->assertSame(3, $t['signups']);
        $this->assertSame(2, $t['first_orders']);
        $this->assertSame(2, $t['app_orders']);
        $this->assertEquals(180.0, $t['app_revenue']);
        $this->assertSame(4, $t['all_orders']);
        $this->assertEquals(290.0, $t['all_revenue']);
    }

    public function test_signup_is_attributed_to_store_of_first_order_even_if_placed_after_the_week(): void
    {
        $c = $this->signup('2026-11-15 20:00');
        $this->order($this->b, $c, '2026-11-17 12:00', 70);
        $this->order($this->a, $c, '2026-11-18 12:00', 30);

        $report = app(WeeklyStoreReport::class)->build(self::WEEK);

        $this->assertSame(1, $this->row($report, 'The Yard')['signups']);
        $this->assertSame(0, $this->row($report, 'Park St.')['signups']);
        $this->assertSame(0, $this->row($report, 'No order yet')['signups']);
    }

    public function test_week_boundaries_use_cairo_time(): void
    {
        // Sunday 23:30 Cairo = 21:30 UTC (still this week); Monday 00:30 Cairo = Sunday 22:30 UTC (next week).
        $this->order($this->a, null, '2026-11-15 23:30', 10);
        $this->order($this->a, null, '2026-11-16 00:30', 20);
        // Monday 00:30 Cairo of the report week = Sunday 22:30 UTC of the previous week.
        $this->order($this->a, null, '2026-11-09 00:30', 5);
        $this->order($this->a, null, '2026-11-08 23:30', 7);

        $this->signup('2026-11-15 23:30');
        $this->signup('2026-11-16 00:30');

        $report = app(WeeklyStoreReport::class)->build(self::WEEK);
        $a = $this->row($report, 'Park St.');
        $this->assertSame(2, $a['all_orders']);
        $this->assertEquals(15.0, $a['all_revenue']);
        $this->assertSame(1, $this->row($report, 'No order yet')['signups']);

        $next = app(WeeklyStoreReport::class)->build('2026-11-16');
        $this->assertSame(1, $this->row($next, 'Park St.')['all_orders']);
        $this->assertEquals(20.0, $this->row($next, 'Park St.')['all_revenue']);
    }

    public function test_app_platforms_follow_the_setting(): void
    {
        $this->order($this->a, null, '2026-11-10 12:00', 10, 'mobile');
        $this->order($this->a, null, '2026-11-10 13:00', 20, 'web');

        Setting::set('app_offer.platforms', ['mobile', 'web']);

        $a = $this->row(app(WeeklyStoreReport::class)->build(self::WEEK), 'Park St.');
        $this->assertSame(2, $a['app_orders']);
        $this->assertEquals(30.0, $a['app_revenue']);
    }

    public function test_csv_export(): void
    {
        $c = $this->signup('2026-11-10 10:00');
        $this->order($this->a, $c, '2026-11-10 12:00', 100.5);

        $service = app(WeeklyStoreReport::class);
        $csv = $service->toCsv($service->build(self::WEEK));
        $lines = array_map('str_getcsv', array_filter(explode("\n", $csv)));

        $this->assertSame('Store', $lines[0][1]);
        $this->assertSame(['2026-11-09 to 2026-11-15', 'Park St.', '1', '1', '1', '100.50', '1', '100.50'], $lines[1]);
        $this->assertSame('No order yet', $lines[3][1]);
        $this->assertSame('Total', end($lines)[1]);
        $this->assertSame('100.50', end($lines)[7]);
    }

    public function test_page_class_exports_csv_for_the_selected_week(): void
    {
        $page = new WeeklyReport();
        $page->week = '2026-11-15';
        $response = $page->exportCsv();

        $this->assertSame('attachment; filename=weekly_report_2026-11-09.csv', $response->headers->get('Content-Disposition'));
        $page->previousWeek();
        $this->assertSame('2026-11-02', $page->week);
    }

    public function test_page_renders_for_staff_with_orders_permission(): void
    {
        Permission::findOrCreate('manage_orders', 'admin');
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('manage_orders');
        $this->order($this->a, null, '2026-11-10 12:00', 100);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');

        $this->assertTrue(WeeklyReport::canAccess());
        Livewire::test(WeeklyReport::class)
            ->set('week', self::WEEK)
            ->assertSee('Park St.')
            ->assertSee('No order yet')
            ->assertSee('Total')
            ->assertSee('Monday 2026-11-09 to Sunday 2026-11-15');
    }

    public function test_page_is_closed_without_permission(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(Admin::factory()->create(), 'admin');

        $this->assertFalse(WeeklyReport::canAccess());
    }
}
