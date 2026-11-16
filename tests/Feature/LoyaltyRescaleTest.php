<?php

namespace Tests\Feature;

use App\Core\Models\Cart;
use App\Core\Models\Customer;
use App\Core\Models\LoyaltyTransaction;
use App\Core\Models\LoyaltyWallet;
use App\Core\Models\Order;
use App\Core\Models\PromoQrCode;
use App\Core\Models\QrCode;
use App\Core\Models\RedeemItem;
use App\Core\Models\Setting;
use App\Core\Models\Admin;
use App\Events\MonthlyDrawEntered;
use App\Filament\Pages\LoyaltySettings;
use App\Services\Loyalty\AlreadyRescaledException;
use App\Services\Loyalty\LoyaltyRescaler;
use App\Services\RefundService;
use Filament\Facades\Filament;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** loyalty:rescale: 1 point per EGP -> 1 point per 10 EGP without losing value. */
class LoyaltyRescaleTest extends TestCase
{
    use RefreshDatabase;

    /** In-memory maintenance mode (never touches storage/framework/down). */
    private MaintenanceMode $maintenance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maintenance = new class implements MaintenanceMode {
            public bool $down = false;

            public function activate(array $payload): void { $this->down = true; }

            public function deactivate(): void { $this->down = false; }

            public function active(): bool { return $this->down; }

            public function data(): array { return []; }
        };
        $this->app->instance(MaintenanceMode::class, $this->maintenance);
    }

    /** Real run inside a maintenance window. */
    private function inMaintenance(callable $fn): mixed
    {
        $this->maintenance->activate([]);
        try {
            return $fn();
        } finally {
            $this->maintenance->deactivate();
        }
    }

    private function wallet(int $earn = 0): LoyaltyWallet
    {
        $wallet = LoyaltyWallet::factory()->create();
        if ($earn > 0) {
            $wallet->addPoints($earn, LoyaltyTransaction::TYPE_EARNED, 'seed');
        }

        return $wallet->refresh();
    }

    private function item(int $cost): RedeemItem
    {
        return RedeemItem::create(['title_json' => ['en' => 'Gift', 'ar' => 'Gift'], 'points_cost' => $cost, 'is_active' => true]);
    }

    private function ledgerSum(LoyaltyWallet $wallet): int
    {
        return (int) LoyaltyTransaction::where('wallet_id', $wallet->id)->sum('points');
    }

    private function rescale(array $options = []): int
    {
        return $this->inMaintenance(fn () => $this->artisan('loyalty:rescale', ['--factor' => 10] + $options)->run());
    }

    private function negativeWallet(int $points): LoyaltyWallet
    {
        $wallet = LoyaltyWallet::factory()->create(['points' => $points]);
        LoyaltyTransaction::create(['wallet_id' => $wallet->id, 'type' => LoyaltyTransaction::TYPE_DEDUCTED, 'points' => $points]);

        return $wallet->refresh();
    }

    public function test_dry_run_writes_nothing(): void
    {
        $wallet = $this->wallet(15);
        $item = $this->item(250);
        Setting::set('loyalty.points_to_egp_rate', 10, 'number', 'loyalty');
        $tx = LoyaltyTransaction::count();
        $settings = Setting::orderBy('id')->get(['key', 'value'])->toArray();

        $this->artisan('loyalty:rescale', ['--factor' => 10, '--dry-run' => true])
            ->expectsOutputToContain('balance sum: 15 -> 2')
            ->expectsOutputToContain('wallets whose balance changes: 1')
            ->expectsOutputToContain('Nothing was written')
            ->assertSuccessful();
        // and it can be repeated
        $this->artisan('loyalty:rescale', ['--factor' => 10, '--dry-run' => true])->assertSuccessful();

        $this->assertSame(15, $wallet->refresh()->points);
        $this->assertSame(250, $item->refresh()->points_cost);
        $this->assertSame($tx, LoyaltyTransaction::count());
        $this->assertSame($settings, Setting::orderBy('id')->get(['key', 'value'])->toArray());
    }

    public function test_factor_below_two_is_refused(): void
    {
        $this->artisan('loyalty:rescale', ['--factor' => 1])->assertFailed();
        $this->assertNull(Setting::get('loyalty.rescaled_factor'));
    }

    public function test_balances_round_up(): void
    {
        $a = $this->wallet(15);
        $b = $this->wallet(10);
        $c = $this->wallet(0);

        $this->assertSame(0, $this->rescale());

        $this->assertSame(2, $a->refresh()->points);
        $this->assertSame(1, $b->refresh()->points);
        $this->assertSame(0, $c->refresh()->points);
        $this->assertSame(0, LoyaltyTransaction::where('wallet_id', $c->id)->count());
    }

    public function test_redeem_costs_round_up_with_minimum_one(): void
    {
        $items = [250 => 25, 255 => 26, 3 => 1, 10 => 1];
        $models = [];
        foreach (array_keys($items) as $cost) {
            $models[$cost] = $this->item($cost);
        }
        $trashed = $this->item(99);
        $trashed->delete();

        $this->rescale();

        foreach ($items as $old => $new) {
            $this->assertSame($new, $models[$old]->refresh()->points_cost, "cost {$old}");
        }
        $this->assertSame(10, RedeemItem::withTrashed()->find($trashed->id)->points_cost);
    }

    public function test_settings_codes_and_carts_are_rescaled(): void
    {
        Setting::set('loyalty.points_to_egp_rate', 10, 'number', 'loyalty');
        Setting::set('loyalty.max_points_per_order', 250, 'number', 'loyalty');
        Setting::set('referral.inviter_points', 30, 'number', 'referral');
        Setting::set('feedback.points_per_rating', 5, 'number', 'feedback');
        // check_in.daily_points / welcome bonus left at their code defaults (3 / env 100)
        $promo = PromoQrCode::create(['code' => 'P-1', 'name' => 'Promo', 'points' => 50, 'is_active' => true, 'available_from' => now()]);
        $qr = QrCode::create(['code' => 'Q-1', 'points_awarded' => 25, 'is_active' => true]);
        $cart = Cart::factory()->create(['points_used' => 95]);

        $this->rescale();

        $this->assertSame(1, Setting::get('loyalty.points_to_egp_rate'));
        $this->assertSame(10, Setting::get('loyalty.egp_per_point'));
        $this->assertSame(25, Setting::get('loyalty.max_points_per_order'));
        $this->assertSame(3, Setting::get('referral.inviter_points'));
        $this->assertSame(3, Setting::get('referral.invitee_points'));
        $this->assertSame(1, Setting::get('feedback.points_per_rating'));
        $this->assertSame(1, Setting::get('check_in.daily_points'));
        $this->assertSame(10, Setting::get('loyalty.welcome_bonus_points'));
        $this->assertSame(1, Setting::get('loyalty.close_to_gift_points'));
        $this->assertSame(10, Setting::get('loyalty.rescaled_factor'));
        $this->assertSame(5, $promo->refresh()->points);
        $this->assertSame(3, $qr->refresh()->points_awarded);
        $this->assertSame(10, $cart->refresh()->points_used);
    }

    public function test_existing_egp_per_point_is_multiplied(): void
    {
        Setting::set('loyalty.egp_per_point', 2, 'number', 'loyalty');
        $this->rescale();
        $this->assertSame(20, Setting::get('loyalty.egp_per_point'));
    }

    public function test_ledger_sum_equals_balance_after(): void
    {
        $a = $this->wallet(158);
        $a->deductPoints(40, LoyaltyTransaction::TYPE_REDEEMED);
        $a->addPoints(33, LoyaltyTransaction::TYPE_ADJUSTED);
        $b = $this->wallet(9);

        $this->rescale();

        foreach ([$a, $b] as $wallet) {
            $wallet->refresh();
            $this->assertSame($this->ledgerSum($wallet), $wallet->points);
        }
        $this->assertSame(16, $a->points); // ceil(151 / 10)
        $this->assertSame(1, $b->points);
    }

    public function test_second_run_is_refused_without_force(): void
    {
        $wallet = $this->wallet(150);
        $this->rescale();
        $this->assertSame(15, $wallet->refresh()->points);

        $this->inMaintenance(fn () => $this->artisan('loyalty:rescale', ['--factor' => 10])
            ->expectsOutputToContain('already rescaled (factor 10 at')
            ->assertFailed());
        $this->assertSame(15, $wallet->refresh()->points);
        $this->assertSame(1, Setting::get('loyalty.points_to_egp_rate'));
        $this->assertSame(10, Setting::get('loyalty.rescaled_factor'));
    }

    public function test_real_run_requires_maintenance_mode(): void
    {
        $wallet = $this->wallet(150);
        $item = $this->item(250);

        $this->artisan('loyalty:rescale', ['--factor' => 10])
            ->expectsOutputToContain('must be in maintenance mode')
            ->expectsOutputToContain('Runbook: php artisan down; stop queue workers + scheduler; run; php artisan up; restart workers')
            ->assertFailed();

        $this->assertSame(150, $wallet->refresh()->points);
        $this->assertSame(250, $item->refresh()->points_cost);
        $this->assertNull(Setting::get('loyalty.rescaled_factor'));
    }

    public function test_racing_second_run_is_refused_inside_the_transaction(): void
    {
        // Both runs passed the command's early marker check; the second one
        // must still be refused by the re-check under the row lock.
        $wallet = $this->wallet(150);
        $item = $this->item(250);
        $rescaler = app(LoyaltyRescaler::class);
        $rescaler->apply(10);
        $rows = LoyaltyTransaction::count();
        $settings = Setting::orderBy('id')->get(['key', 'value'])->toArray();

        try {
            $rescaler->apply(10);
            $this->fail('second apply() should have been refused');
        } catch (AlreadyRescaledException) {
        }

        $this->assertSame(15, $wallet->refresh()->points);
        $this->assertSame(25, $item->refresh()->points_cost);
        $this->assertSame($rows, LoyaltyTransaction::count());
        $this->assertSame($settings, Setting::orderBy('id')->get(['key', 'value'])->toArray());
    }

    public function test_negative_balances_round_toward_zero(): void
    {
        $debt = $this->negativeWallet(-25);
        $small = $this->negativeWallet(-5);

        $this->artisan('loyalty:rescale', ['--factor' => 10, '--dry-run' => true])
            ->expectsOutputToContain('negative balance (rescaled toward zero): 2')
            ->assertSuccessful();
        $this->rescale();

        $this->assertSame(-2, $debt->refresh()->points);
        $this->assertSame(0, $small->refresh()->points);
        foreach ([$debt, $small] as $wallet) {
            $this->assertSame($this->ledgerSum($wallet), $wallet->points);
        }

        // A later earn pays the debt off first; only what is left can expire.
        $debt->addPoints(30, LoyaltyTransaction::TYPE_EARNED);
        $this->assertSame(28, $debt->refresh()->points);
        $this->travel(13)->months();
        $this->artisan('loyalty:expire-points')->assertSuccessful();
        $this->assertSame(0, $debt->refresh()->points);
        $this->assertSame(-28, (int) LoyaltyTransaction::where('wallet_id', $debt->id)->where('type', 'expired')->sum('points'));
        $this->assertSame($this->ledgerSum($debt), $debt->points);
    }

    public function test_stale_admin_settings_form_cannot_restore_old_scale_values(): void
    {
        Permission::findOrCreate('manage_settings', 'admin');
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('manage_settings');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');
        Setting::set('check_in.daily_points', 30, 'number', 'check_in');

        $page = Livewire::test(LoyaltySettings::class); // opened before the rescale
        $this->rescale();
        $this->assertSame(3, Setting::get('check_in.daily_points'));

        $page->call('save')->assertNotified(__('system.loyalty_settings_stale'));
        $this->assertSame(3, Setting::get('check_in.daily_points'));

        // a freshly loaded form saves normally
        Livewire::test(LoyaltySettings::class)
            ->set('data.check_in_daily_points', 4)
            ->call('save')
            ->assertNotified(__('system.loyalty_settings_saved'));
        $this->assertSame(4, Setting::get('check_in.daily_points'));
    }

    public function test_expiry_after_rescale_only_takes_carried_over_lots(): void
    {
        $wallet = $this->wallet(100);
        // that first earn is already past its expiry (sweep not run yet)
        LoyaltyTransaction::where('wallet_id', $wallet->id)->update(['expires_at' => now()->subDay()]);
        $wallet->addPoints(55, LoyaltyTransaction::TYPE_EARNED);
        $wallet->deductPoints(30, LoyaltyTransaction::TYPE_REDEEMED);
        $this->assertSame(125, $wallet->refresh()->points);

        $this->rescale();
        // ceil(125/10) = 13: lots floor(70/10)=7 (overdue) and 5 (in a year), +1 non-expiring
        $this->assertSame(13, $wallet->refresh()->points);

        $this->artisan('loyalty:expire-points')->assertSuccessful();
        $this->assertSame(6, $wallet->refresh()->points);

        $this->travel(13)->months();
        $this->artisan('loyalty:expire-points')->assertSuccessful();
        $this->assertSame(1, $wallet->refresh()->points);
        $this->artisan('loyalty:expire-points')->assertSuccessful();
        $this->assertSame(1, $wallet->refresh()->points);
        $this->assertSame($this->ledgerSum($wallet), $wallet->points);
    }

    public function test_expiry_after_rescale_cannot_go_negative(): void
    {
        $spent = $this->wallet(200);
        // non-expiring credit spent: old-scale consumption (300) exceeds the
        // old expiring pool (0), which would otherwise shield new earns
        $empty = $this->wallet();
        $empty->addPoints(300, LoyaltyTransaction::TYPE_ADJUSTED);
        $empty->deductPoints(300, LoyaltyTransaction::TYPE_REDEEMED);

        $this->rescale();
        $spent->refresh()->deductPoints(20, LoyaltyTransaction::TYPE_REDEEMED);
        // old-scale history must not shield (or over-expire) new-scale earns
        $empty->refresh()->addPoints(7, LoyaltyTransaction::TYPE_EARNED);

        $this->travel(13)->months();
        $this->artisan('loyalty:expire-points')->assertSuccessful();

        $this->assertSame(0, $spent->refresh()->points);
        $this->assertSame(0, LoyaltyTransaction::where('wallet_id', $spent->id)->where('type', 'expired')->count());
        $this->assertSame(0, $empty->refresh()->points);
        $this->assertSame(-7, (int) LoyaltyTransaction::where('wallet_id', $empty->id)->where('type', 'expired')->sum('points'));
        foreach ([$spent, $empty] as $wallet) {
            $this->assertSame($this->ledgerSum($wallet), $wallet->points);
        }
    }

    public function test_earning_after_rescale_is_one_point_per_ten_egp(): void
    {
        Event::fake([MonthlyDrawEntered::class]);
        $customer = Customer::factory()->create();
        $this->rescale();

        $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'received', 'total' => 129.90, 'platform' => 'mobile']);
        $order->update(['status' => 'completed']);

        $this->assertSame(12, (int) $customer->loyaltyWallet()->first()->points);
    }

    public function test_refund_reverses_a_pre_rescale_earn_in_the_new_scale(): void
    {
        Event::fake([MonthlyDrawEntered::class]);
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'received', 'total' => 155, 'platform' => 'mobile']);
        $order->update(['status' => 'completed']);
        $wallet = $customer->loyaltyWallet()->first();
        $this->assertSame(155, $wallet->points);

        $this->rescale();

        $method = new \ReflectionMethod(RefundService::class, 'pointsToReverse');
        $this->assertSame(15, $method->invoke(app(RefundService::class), $wallet->refresh(), $order));
    }

    public function test_customer_history_hides_rescale_rows(): void
    {
        $wallet = $this->wallet(1500);
        $this->assertSame(0, $this->rescale());

        $res = $this->actingAs($wallet->customer, 'api')->getJson('/api/v1/loyalty')->assertOk();
        $types = collect($res->json('data.transactions'))->pluck('type')->unique()->values()->all();
        $this->assertSame(['earned'], $types);
        $this->assertSame(150, (int) $wallet->refresh()->points);
    }
}
