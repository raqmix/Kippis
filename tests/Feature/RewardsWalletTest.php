<?php

namespace Tests\Feature;

use App\Core\Models\Admin;
use App\Core\Models\Cart;
use App\Core\Models\Category;
use App\Core\Models\Customer;
use App\Core\Models\CustomerRedeemWallet;
use App\Core\Models\PaymentMethod;
use App\Core\Models\Product;
use App\Core\Models\Setting;
use App\Core\Models\Store;
use App\Core\Repositories\OrderRepository;
use App\Filament\Pages\RedeemGift;
use App\Services\Rewards\CounterRedemptionService;
use App\Services\Rewards\RewardIssuer;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Brief section B: rewards wallet. */
class RewardsWalletTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private RewardIssuer $issuer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::factory()->create();
        $this->issuer = app(RewardIssuer::class);
    }

    private function counterGift(array $overrides = []): CustomerRedeemWallet
    {
        return $this->issuer->issue(
            $this->customer, 'admin', 'counter', ['note' => 'Cookie'],
            ['en' => 'Free cookie', 'ar' => 'كوكي مجاني'],
            $overrides['ttl'] ?? 30, $overrides['ref'] ?? null,
        );
    }

    public function test_issue_is_idempotent_per_source_ref_and_generates_code(): void
    {
        $a = $this->issuer->issue($this->customer, 'streak', 'counter', [], ['en' => 'Gift'], 30, 'streak:2026-11');
        $b = $this->issuer->issue($this->customer, 'streak', 'counter', [], ['en' => 'Gift'], 30, 'streak:2026-11');
        $c = $this->issuer->issue($this->customer, 'streak', 'counter', [], ['en' => 'Gift'], 30, 'streak:2026-12');

        $this->assertSame($a->id, $b->id);
        $this->assertNotSame($a->id, $c->id);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $a->code);
        $this->assertNull($a->redeem_item_id);
        $this->assertSame(0, $a->points_spent);
        $this->assertTrue($a->expires_at->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_free_coffee_from_settings(): void
    {
        $cat = Category::factory()->create();
        Setting::set('rewards.free_coffee.category_ids', [$cat->id], 'json', 'rewards');
        Setting::set('rewards.wallet_ttl_days', 7, 'number', 'rewards');

        $gift = $this->issuer->issueFreeItemFromSettings($this->customer, 'referral', 'free_coffee', 'referral:1');

        $this->assertSame('free_item', $gift->reward_type);
        $this->assertSame([$cat->id], $gift->reward_config['category_ids']);
        $this->assertEquals(130, $gift->reward_config['max_value']);
        $this->assertSame('Free coffee', $gift->getTitle('en'));
        $this->assertTrue($gift->expires_at->between(now()->addDays(6), now()->addDays(8)));
    }

    public function test_rewards_endpoint_lists_available_then_recent_past(): void
    {
        $counter = $this->counterGift();
        $used = $this->counterGift();
        $used->update(['status' => 'applied', 'used_at' => now()->subDays(2)]);
        $oldUsed = $this->counterGift();
        $oldUsed->update(['status' => 'applied', 'used_at' => now()->subDays(40)]);
        $expiredNow = $this->counterGift();
        $expiredNow->update(['expires_at' => now()->subDay()]);
        $freeItem = $this->issuer->issue($this->customer, 'birthday', 'free_item',
            ['category_ids' => [1], 'max_value' => 170], ['en' => 'Birthday drink'], 5, 'birthday:2026');
        $otherCustomers = $this->issuer->issue(Customer::factory()->create(), 'admin', 'counter', [], ['en' => 'x']);

        $response = $this->actingAs($this->customer, 'api')->getJson('/api/v1/rewards')->assertOk();
        $rows = collect($response->json('data.rewards'));

        $this->assertSame(
            [$freeItem->id, $counter->id],
            $rows->where('status', 'available')->pluck('id')->values()->all(),
        );
        $this->assertSame('available', $rows[0]['status']);
        $this->assertTrue($rows->firstWhere('id', $freeItem->id)['usable_in_app']);
        $c = $rows->firstWhere('id', $counter->id);
        $this->assertFalse($c['usable_in_app']);
        $this->assertSame('counter', $c['reward_type']);
        $this->assertSame('admin', $c['source']);
        $this->assertSame($counter->code, $c['code']);
        $this->assertStringEndsWith('/admin/redeem-gift?code=' . $counter->code, $c['qr_payload']);
        $this->assertSame('Free cookie', $c['title']);
        $this->assertSame('used', $rows->firstWhere('id', $used->id)['status']);
        $this->assertSame('expired', $rows->firstWhere('id', $expiredNow->id)['status']);
        $this->assertNull($rows->firstWhere('id', $oldUsed->id));
        $this->assertNull($rows->firstWhere('id', $otherCustomers->id));
        // Available ones come first.
        $this->assertSame(['available', 'available'], $rows->take(2)->pluck('status')->all());
    }

    public function test_legacy_wallet_items_endpoint_still_works_with_new_fields(): void
    {
        $gift = $this->counterGift();
        $this->actingAs($this->customer, 'api')->getJson('/api/v1/loyalty/wallet-items')
            ->assertOk()
            ->assertJsonPath('data.wallet_items.0.id', $gift->id)
            ->assertJsonPath('data.wallet_items.0.redeem_item_id', null)
            ->assertJsonPath('data.wallet_items.0.title', 'Free cookie')
            ->assertJsonPath('data.wallet_items.0.reward_type', 'counter')
            ->assertJsonPath('data.wallet_items.0.usable_in_app', false);
    }

    public function test_counter_gift_cannot_be_applied_in_app_cart(): void
    {
        $store = Store::factory()->create();
        $this->actingAs($this->customer, 'api')->postJson('/api/v1/cart/init', ['store_id' => $store->id]);
        $gift = $this->counterGift();

        $this->actingAs($this->customer, 'api')
            ->postJson('/api/v1/cart/apply-wallet-item', ['wallet_item_id' => $gift->id])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_counter_redeem_via_admin_page_marks_used_once(): void
    {
        Permission::findOrCreate('manage_orders', 'admin');
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('manage_orders');
        $gift = $this->counterGift();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');

        // The QR encodes the full URL; the page accepts it in ?code=.
        Livewire::withQueryParams(['code' => $gift->qrPayload()])
            ->test(RedeemGift::class)
            ->assertSet('entryId', $gift->id)
            ->assertSee('Free cookie')
            ->call('markUsed');

        $gift->refresh();
        $this->assertSame('applied', $gift->status);
        $this->assertNotNull($gift->used_at);
        $this->assertSame($admin->id, $gift->used_by_admin_id);

        // Lower-case code with a dash, typed by hand: found, but already used.
        Livewire::test(RedeemGift::class)
            ->set('code', strtolower(substr($gift->code, 0, 4) . '-' . substr($gift->code, 4)))
            ->call('lookup')
            ->assertSet('entryId', $gift->id)
            ->assertSee(__('rewards.already_used'));

        $this->expectException(\DomainException::class);
        app(CounterRedemptionService::class)->markUsed($gift, $admin);
    }

    public function test_expired_gift_cannot_be_redeemed_or_applied(): void
    {
        $gift = $this->counterGift();
        $gift->update(['expires_at' => now()->subMinute()]);

        try {
            app(CounterRedemptionService::class)->markUsed($gift, null);
            $this->fail('expired gift was redeemed');
        } catch (\DomainException $e) {
            $this->assertSame(__('rewards.expired'), $e->getMessage());
        }
        $this->assertSame('expired', $gift->fresh()->status);

        $store = Store::factory()->create();
        $this->actingAs($this->customer, 'api')->postJson('/api/v1/cart/init', ['store_id' => $store->id]);
        $this->actingAs($this->customer, 'api')
            ->postJson('/api/v1/cart/apply-wallet-item', ['wallet_item_id' => $gift->id])
            ->assertStatus(422);
    }

    public function test_free_item_is_single_use_and_restored_when_order_cancelled(): void
    {
        $payment = PaymentMethod::create(['name' => 'Cash', 'code' => 'cash', 'is_active' => true]);
        $store = Store::factory()->create();
        $cat = Category::factory()->create();
        $coffee = Product::factory()->create(['base_price' => 90, 'category_id' => $cat->id]);
        $gift = $this->issuer->issue($this->customer, 'streak', 'free_item',
            ['category_ids' => [$cat->id], 'max_value' => 130], ['en' => 'Free coffee'], 30, 'streak:1');

        $this->actingAs($this->customer, 'api')->postJson('/api/v1/cart/init', ['store_id' => $store->id]);
        $this->actingAs($this->customer, 'api')->postJson('/api/v1/cart/items', [
            'item_type' => 'product', 'product_id' => $coffee->id, 'quantity' => 1,
        ]);
        $this->actingAs($this->customer, 'api')
            ->postJson('/api/v1/cart/apply-wallet-item', ['wallet_item_id' => $gift->id])
            ->assertOk()
            ->assertJsonPath('data.wallet_item.reward_type', 'free_item')
            ->assertJsonPath('data.total', 0);

        $cart = Cart::where('customer_id', $this->customer->id)->latest('id')->first();
        $order = app(OrderRepository::class)->createFromCart($cart, $payment->id);
        $this->assertSame('applied', $gift->fresh()->status);

        // Spent: can't be applied again.
        $this->actingAs($this->customer, 'api')->postJson('/api/v1/cart/init', ['store_id' => $store->id]);
        $this->actingAs($this->customer, 'api')
            ->postJson('/api/v1/cart/apply-wallet-item', ['wallet_item_id' => $gift->id])
            ->assertStatus(422);

        // Cancelled before completion: back in the wallet.
        $order->update(['status' => 'cancelled']);
        $gift->refresh();
        $this->assertSame('available', $gift->status);
        $this->assertNull($gift->used_order_id);
    }
}
