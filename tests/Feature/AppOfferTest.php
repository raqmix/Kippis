<?php

namespace Tests\Feature;

use App\Core\Models\Cart;
use App\Core\Models\Category;
use App\Core\Models\Customer;
use App\Core\Models\CustomerRedeemWallet;
use App\Core\Models\Order;
use App\Core\Models\PaymentMethod;
use App\Core\Models\Product;
use App\Core\Models\PromoCode;
use App\Core\Models\Setting;
use App\Core\Models\Store;
use App\Core\Repositories\OrderRepository;
use App\Jobs\PushOrderToFoodics;
use App\Services\Rewards\RewardIssuer;
use App\Support\ClientPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Brief section A: platform detection + app-only discount. */
class AppOfferTest extends TestCase
{
    use RefreshDatabase;

    private const MOBILE = ['X-Kippis-Platform' => 'android'];

    private Store $store;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::factory()->create();
        $this->customer = Customer::factory()->create();
    }

    /** Builds a cart through the API with the given headers. */
    private function cartWith(array $products, array $headers = []): Cart
    {
        $this->actingAs($this->customer, 'api')->withHeaders($headers)
            ->postJson('/api/v1/cart/init', ['store_id' => $this->store->id])->assertSuccessful();
        foreach ($products as [$product, $qty]) {
            $this->actingAs($this->customer, 'api')->withHeaders($headers)
                ->postJson('/api/v1/cart/items', [
                    'item_type' => 'product',
                    'product_id' => $product->id,
                    'quantity' => $qty,
                ])->assertSuccessful();
        }

        return Cart::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
    }

    private function promo(string $code, float $percent): PromoCode
    {
        return PromoCode::factory()->create([
            'code' => $code,
            'discount_type' => 'percentage',
            'discount_value' => $percent,
            'minimum_order_amount' => 0,
            'active' => true,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addDay(),
        ]);
    }

    public function test_platform_resolves_from_header_user_agent_and_kiosk_path(): void
    {
        $resolve = fn (string $uri, array $server) => ClientPlatform::resolve(Request::create($uri, 'GET', [], [], [], $server));

        $this->assertSame('mobile', $resolve('/api/v1/cart', ['HTTP_X_KIPPIS_PLATFORM' => 'ios']));
        $this->assertSame('mobile', $resolve('/api/v1/cart', ['HTTP_X_KIPPIS_PLATFORM' => 'Android']));
        $this->assertSame('web', $resolve('/api/v1/cart', ['HTTP_X_KIPPIS_PLATFORM' => 'web']));
        $this->assertSame('mobile', $resolve('/api/v1/cart', ['HTTP_USER_AGENT' => 'Dart/3.5 (dart:io)']));
        $this->assertSame('unknown', $resolve('/api/v1/cart', ['HTTP_USER_AGENT' => 'Mozilla/5.0']));
        $this->assertSame('kiosk', $resolve('/api/v1/kiosk/cart', ['HTTP_USER_AGENT' => 'Dart/3.5 (dart:io)']));
    }

    public function test_app_discount_applies_to_mobile_cart_only(): void
    {
        $product = Product::factory()->create(['base_price' => 100]);

        $mobile = $this->cartWith([[$product, 1]], ['User-Agent' => 'Dart/3.5 (dart:io)']);
        $this->assertSame('mobile', $mobile->platform);
        $this->assertEquals(30.00, (float) $mobile->app_discount);
        $this->assertEquals(70.00, (float) $mobile->total);
        $this->assertSame('app_offer', $mobile->discount_source);

        $mobile->update(['status' => 'abandoned']);
        $other = Customer::factory()->create();
        $this->customer = $other;
        $web = $this->cartWith([[$product, 1]], ['X-Kippis-Platform' => 'web']);
        $this->assertSame('web', $web->platform);
        $this->assertEquals(0.0, (float) $web->app_discount);
        $this->assertEquals(100.00, (float) $web->total);
        $this->assertNull($web->discount_source);
    }

    public function test_headerless_request_on_an_app_cart_drops_the_app_discount(): void
    {
        $product = Product::factory()->create(['base_price' => 100]);
        $cart = $this->cartWith([[$product, 1]], self::MOBILE);
        $this->assertEquals(30.00, (float) $cart->app_discount);

        // Website: no X-Kippis-Platform header, browser UA (withHeaders is
        // sticky, so drop the app header first).
        $this->flushHeaders();
        $this->actingAs($this->customer, 'api')->withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->postJson('/api/v1/cart/items', [
                'item_type' => 'product', 'product_id' => $product->id, 'quantity' => 1,
            ])->assertSuccessful();

        $cart->refresh();
        $this->assertSame('unknown', $cart->platform);
        $this->assertEquals(0.0, (float) $cart->app_discount);
        $this->assertEquals(200.00, (float) $cart->total);

        // Checkout re-stamps too: app add, then a header-less checkout.
        $cart->update(['platform' => 'mobile']);
        $payment = PaymentMethod::create(['name' => 'Cash', 'code' => 'cash', 'is_active' => true]);
        $this->actingAs($this->customer, 'api')->postJson('/api/v1/orders/checkout', ['payment_method_id' => $payment->id]);
        $this->assertSame('unknown', $cart->fresh()->platform);
        $this->assertEquals(0.0, (float) $cart->fresh()->app_discount);
    }

    public function test_payment_callback_keeps_the_app_discount(): void
    {
        $payment = PaymentMethod::create(['name' => 'Card', 'code' => 'card', 'is_active' => true]);
        $product = Product::factory()->create(['base_price' => 100]);
        $cart = $this->cartWith([[$product, 1]], self::MOBILE);
        $this->assertEquals(70.00, (float) $cart->total);

        $gateway = \Mockery::mock(\App\Services\MastercardPaymentService::class);
        $gateway->shouldReceive('pay')->once()->andReturn(['success' => true]);
        $this->app->instance(\App\Services\MastercardPaymentService::class, $gateway);
        \Illuminate\Support\Facades\Cache::put('mpgs_pending_REF1', [
            'customer_id' => $this->customer->id,
            'gateway_order_id' => 'gw_1',
            'amount' => '70.00',
            'currency' => 'EGP',
            'session_id' => 'S1',
            'payment_method_id' => $payment->id,
            'store_id' => $this->store->id,
        ], 600);

        // No client headers, as when the card issuer redirects the browser back.
        $this->flushHeaders();
        $this->post('/api/v1/payment/mastercard/3ds-return/REF1')->assertOk();

        $order = Order::where('customer_id', $this->customer->id)->firstOrFail();
        $this->assertSame('mobile', $order->platform);
        $this->assertEquals(30.00, (float) $order->app_discount);
        $this->assertEquals(70.00, (float) $order->total);
    }

    public function test_cart_response_exposes_app_offer_fields(): void
    {
        $product = Product::factory()->create(['base_price' => 80]);
        $this->cartWith([[$product, 1]], self::MOBILE);

        $this->actingAs($this->customer, 'api')->withHeaders(self::MOBILE)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.original_total', 80)
            ->assertJsonPath('data.app_discount', 24)
            ->assertJsonPath('data.app_discount_percent', 30)
            ->assertJsonPath('data.discount_source', 'app_offer')
            ->assertJsonPath('data.discount', 24)
            ->assertJsonPath('data.total', 56);
    }

    public function test_percent_setting_and_switch_off(): void
    {
        $product = Product::factory()->create(['base_price' => 100]);
        Setting::set('app_offer.percent', 20, 'number', 'app_offer');
        $cart = $this->cartWith([[$product, 1]], self::MOBILE);
        $this->assertEquals(20.00, (float) $cart->app_discount);

        Setting::set('app_offer.enabled', false, 'boolean', 'app_offer');
        $cart->recalculate();
        $this->assertEquals(0.0, (float) $cart->fresh()->app_discount);
        $this->assertEquals(100.00, (float) $cart->fresh()->total);

        $this->getJson('/api/v1/app-config')
            ->assertOk()
            ->assertJsonPath('data.app_offer.enabled', false)
            ->assertJsonPath('data.app_offer.percent', 20);
    }

    public function test_app_config_is_public(): void
    {
        $this->getJson('/api/v1/app-config')
            ->assertOk()
            ->assertJsonPath('data.app_offer.enabled', true)
            ->assertJsonPath('data.app_offer.percent', 30)
            ->assertJsonStructure(['data' => ['app_offer' => ['enabled', 'percent', 'label_en', 'label_ar']]]);
    }

    public function test_larger_of_promo_and_app_discount_wins_with_snapshot(): void
    {
        $product = Product::factory()->create(['base_price' => 100]);
        $this->promo('SMALL10', 10);
        $this->promo('BIG50', 50);
        $this->cartWith([[$product, 1]], self::MOBILE);

        // Weaker code: app discount stays, the code is recorded as suppressed.
        $this->actingAs($this->customer, 'api')->withHeaders(self::MOBILE)
            ->postJson('/api/v1/cart/apply-promo', ['code' => 'SMALL10'])
            ->assertOk()
            ->assertJsonPath('data.discount_source', 'app_offer')
            ->assertJsonPath('data.app_discount', 30)
            ->assertJsonPath('data.total', 70)
            ->assertJsonPath('data.app_offer.beats_promotion', true);
        $cart = Cart::where('customer_id', $this->customer->id)->latest('id')->first();
        $this->assertSame([], $cart->applied_promotions_snapshot);
        $this->assertNotEmpty($cart->app_offer_snapshot['suppressed_promotions']);
        $this->assertEquals(10.0, $cart->app_offer_snapshot['promo_discount']);

        // Stronger code wins instead.
        $this->actingAs($this->customer, 'api')->withHeaders(self::MOBILE)
            ->postJson('/api/v1/cart/apply-promo', ['code' => 'BIG50'])
            ->assertOk()
            ->assertJsonPath('data.discount_source', 'promotion')
            ->assertJsonPath('data.app_discount', 0)
            ->assertJsonPath('data.total', 50);
        $cart->refresh();
        $this->assertNotEmpty($cart->applied_promotions_snapshot);
        $this->assertEquals(30.0, $cart->app_offer_snapshot['app_discount']);
    }

    public function test_gift_item_is_excluded_from_app_discount_base(): void
    {
        $coffeeCat = Category::factory()->create();
        $coffee = Product::factory()->create(['base_price' => 100, 'category_id' => $coffeeCat->id]);
        $cake = Product::factory()->create(['base_price' => 50]);
        $cart = $this->cartWith([[$coffee, 1], [$cake, 1]], self::MOBILE);

        $gift = app(RewardIssuer::class)->issue(
            $this->customer, 'admin', 'free_item',
            ['category_ids' => [$coffeeCat->id], 'max_value' => 130],
            ['en' => 'Free coffee', 'ar' => 'قهوة مجانية'],
        );
        $this->actingAs($this->customer, 'api')->withHeaders(self::MOBILE)
            ->postJson('/api/v1/cart/apply-wallet-item', ['wallet_item_id' => $gift->id])
            ->assertOk();

        $cart->refresh();
        $this->assertEquals(100.00, (float) $cart->wallet_discount);
        $this->assertEquals(15.00, (float) $cart->app_discount); // 30% of (150 - 100)
        $this->assertEquals(115.00, (float) $cart->discount);
        $this->assertEquals(35.00, (float) $cart->total);
    }

    public function test_free_item_reward_is_capped_and_takes_most_expensive_line(): void
    {
        $coffeeCat = Category::factory()->create();
        $cheap = Product::factory()->create(['base_price' => 60, 'category_id' => $coffeeCat->id]);
        $dear = Product::factory()->create(['base_price' => 200, 'category_id' => $coffeeCat->id]);
        $cart = $this->cartWith([[$cheap, 2], [$dear, 1]], ['X-Kippis-Platform' => 'web']);

        $gift = app(RewardIssuer::class)->issue(
            $this->customer, 'admin', 'free_item',
            ['category_ids' => [$coffeeCat->id], 'max_value' => 130],
            ['en' => 'Free coffee'],
        );
        $cart->update(['wallet_item_id' => $gift->id]);
        $cart->recalculate();
        $cart->refresh();

        $this->assertEquals(320.00, (float) $cart->subtotal);
        $this->assertEquals(130.00, (float) $cart->wallet_discount);
        $this->assertEquals(190.00, (float) $cart->total);

        // No qualifying line: worth nothing.
        $gift->update(['reward_config' => ['category_ids' => [999999], 'max_value' => 130]]);
        $cart->recalculate();
        $this->assertEquals(0.0, (float) $cart->fresh()->wallet_discount);
    }

    public function test_order_from_mobile_cart_keeps_platform_and_app_discount_and_foodics_discount_matches(): void
    {
        $payment = PaymentMethod::create(['name' => 'Cash', 'code' => 'cash', 'is_active' => true]);
        $coffeeCat = Category::factory()->create();
        $coffee = Product::factory()->create(['base_price' => 100, 'category_id' => $coffeeCat->id]);
        $cake = Product::factory()->create(['base_price' => 50]);
        $cart = $this->cartWith([[$coffee, 1], [$cake, 1]], self::MOBILE);
        $gift = app(RewardIssuer::class)->issue(
            $this->customer, 'admin', 'free_item',
            ['category_ids' => [$coffeeCat->id], 'max_value' => 130], ['en' => 'Free coffee'],
        );
        $cart->update(['wallet_item_id' => $gift->id]);
        $cart->recalculate();
        $cart->refresh();

        $order = app(OrderRepository::class)->createFromCart($cart, $payment->id);
        $order->refresh();

        $this->assertSame('mobile', $order->platform);
        $this->assertSame('app_offer', $order->discount_source);
        $this->assertEquals(15.00, (float) $order->app_discount);
        $this->assertEquals(100.00, (float) $order->wallet_discount); // fillable fix
        $this->assertSame($gift->id, $order->wallet_item_id);           // fillable fix
        $this->assertEquals(115.00, (float) $order->discount);
        $this->assertEquals((float) $cart->total, (float) $order->total);
        $this->assertEquals(35.00, (float) $order->total);
        $this->assertNotEmpty($order->app_offer_snapshot);
        $this->assertSame(CustomerRedeemWallet::STATUS_APPLIED, $gift->fresh()->status);
        $this->assertSame($order->id, $gift->fresh()->used_order_id);

        $job = new PushOrderToFoodics($order->id);
        $method = (new \ReflectionClass($job))->getMethod('mapToFoodicsPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($job, $order, 'branch-test');
        $this->assertEquals(115.00, $payload['discount_amount']);
        $this->assertEquals(35.00, round($payload['total'] - $payload['discount_amount'], 2));
    }

    public function test_order_fillable_persists_points_and_snapshot_columns(): void
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'points_used' => 50,
            'points_discount' => 5,
            'applied_promotions_snapshot' => [['id' => 1]],
            'platform' => 'mobile',
            'app_discount' => 12.5,
            'discount_source' => 'app_offer',
        ])->fresh();

        $this->assertSame(50, $order->points_used);
        $this->assertEquals(5.0, (float) $order->points_discount);
        $this->assertSame([['id' => 1]], $order->applied_promotions_snapshot);
        $this->assertSame('mobile', $order->platform);
        $this->assertEquals(12.5, (float) $order->app_discount);
    }

    public function test_admin_settings_page_saves_app_offer_and_reward_keys(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('manage_settings', 'admin');
        $admin = \App\Core\Models\Admin::factory()->create();
        $admin->givePermissionTo('manage_settings');
        $cat = Category::factory()->create();
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');

        \Livewire\Livewire::test(\App\Filament\Pages\AppRewardsSettings::class)
            ->assertSet('data.app_offer_percent', 30)
            ->set('data.app_offer_percent', 25)
            ->set('data.free_coffee_category_ids', [$cat->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(25, Setting::get('app_offer.percent'));
        $this->assertSame([$cat->id], Setting::get('rewards.free_coffee.category_ids'));
        $this->assertSame(130, Setting::get('rewards.free_coffee.max_value'));
    }
}
