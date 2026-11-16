<?php

namespace Tests\Feature;

use App\Core\Models\Admin;
use App\Core\Models\Customer;
use App\Core\Models\Order;
use App\Core\Models\Store;
use App\Filament\Pages\MonthlyDraw;
use App\Filament\Resources\PointsMultiplierRuleResource\Pages\ListPointsMultiplierRules;
use App\Services\Loyalty\MonthlyDrawService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Brief section C: Monthly Big Win export. */
class MonthlyDrawTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $cairo, string $platform, string $status, Customer $customer, Store $store): Order
    {
        return Order::factory()->create([
            'customer_id' => $customer->id, 'store_id' => $store->id, 'status' => $status,
            'platform' => $platform, 'created_at' => Carbon::parse($cairo, 'Africa/Cairo')->utc(),
            'updated_at' => Carbon::parse($cairo, 'Africa/Cairo')->utc(),
        ]);
    }

    public function test_csv_lists_completed_app_orders_of_the_cairo_month(): void
    {
        $store = Store::factory()->create(['name' => 'Park St.']);
        $ann = Customer::factory()->create(['name' => 'Ann', 'country_code' => '+20', 'phone' => '1000000001']);
        $evil = Customer::factory()->create(['name' => '=HYPERLINK("x")', 'country_code' => '+20', 'phone' => '1000000002']);

        $in1 = $this->order('2026-11-01 00:10', 'mobile', 'completed', $ann, $store);   // 30 Oct UTC, Nov in Cairo
        $in2 = $this->order('2026-11-30 23:50', 'mobile', 'completed', $evil, $store);  // Nov 30 21:50 UTC
        $this->order('2026-10-31 23:50', 'mobile', 'completed', $ann, $store);          // October in Cairo
        $this->order('2026-12-01 00:10', 'mobile', 'completed', $ann, $store);          // December in Cairo
        $this->order('2026-11-10 12:00', 'web', 'completed', $ann, $store);             // not an app order
        $this->order('2026-11-10 12:00', 'kiosk', 'completed', $ann, $store);
        $this->order('2026-11-10 12:00', 'mobile', 'cancelled', $ann, $store);          // not completed
        Order::factory()->create(['customer_id' => null, 'status' => 'completed', 'platform' => 'mobile', 'created_at' => Carbon::parse('2026-11-10', 'Africa/Cairo'), 'updated_at' => Carbon::parse('2026-11-10', 'Africa/Cairo')]);

        $csv = app(MonthlyDrawService::class)->csv('2026-11');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_values(array_filter(preg_split('/\r?\n/', substr($csv, 3))));

        $this->assertSame('order_id,date,customer_id,name,phone,store', $lines[0]);
        $this->assertCount(3, $lines);
        $this->assertSame("{$in1->id},\"2026-11-01 00:10\",{$ann->id},Ann,\"+20 1000000001\",\"Park St.\"", $lines[1]);
        $this->assertStringStartsWith("{$in2->id},\"2026-11-30 23:50\",{$evil->id},\"'=HYPERLINK", $lines[2]);
    }

    public function test_random_winner_comes_from_the_entries(): void
    {
        $store = Store::factory()->create();
        $customer = Customer::factory()->create();
        $a = $this->order('2026-11-05 10:00', 'mobile', 'completed', $customer, $store);
        $this->order('2026-11-05 10:00', 'web', 'completed', $customer, $store);

        $this->assertTrue(app(MonthlyDrawService::class)->pickWinner('2026-11')->is($a));
        $this->assertNull(app(MonthlyDrawService::class)->pickWinner('2026-09'));
    }

    public function test_filament_pages_render_and_export(): void
    {
        Permission::findOrCreate('manage_loyalty', 'admin');
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('manage_loyalty');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');

        $store = Store::factory()->create();
        $this->order('2026-11-05 10:00', 'mobile', 'completed', Customer::factory()->create(['name' => 'Winner']), $store);

        Livewire::test(MonthlyDraw::class)
            ->set('month', '2026-11')
            ->assertSee('1')
            ->call('exportCsv')
            ->assertFileDownloaded('monthly-draw-2026-11.csv')
            ->call('pickWinner')
            ->assertSee('Winner');

        \App\Core\Models\PointsMultiplierRule::create(['name' => 'Monday Refill', 'multiplier' => 2, 'days_of_week' => [1]]);
        Livewire::test(ListPointsMultiplierRules::class)->assertSee('Monday Refill');
    }
}
