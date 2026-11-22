<?php

namespace Tests\Feature;

use App\Core\Models\Cart;
use App\Core\Models\Category;
use App\Core\Models\CupCardEvent;
use App\Core\Models\Customer;
use App\Core\Models\Order;
use App\Core\Models\Product;
use App\Core\Models\PromoCode;
use App\Core\Models\Setting;
use App\Core\Models\Store;
use App\Events\CupCardCompleted;
use App\Events\CupCardStamped;
use App\Services\CupCard\CupCardService;
use App\Services\Rewards\RewardIssuer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** KIP-36: cup card ("every cup gets cheaper"). */
class CupCardTest extends TestCase
{
    use RefreshDatabase;

    private const MOBILE = ['X-Kippis-Platform' => 'android'];

    private Store $store;

    private Customer $customer;

    private Category $coffee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->coffee = Category::factory()->create();
        Setting::set('cup_card.enabled', true, 'boolean', 'cup_card');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function product(float $price, ?Category $category = null): Product
    {
        return Product::factory()->create(['base_price' => $price, 'category_id' => ($category ?? $this->coffee)->id]);
    }

    private function stamps(int $n): void
    {
        $this->customer->forceFill(['cup_card_stamps' => $n, 'cup_card_last_stamp_at' => $n ? now() : null])->save();
    }

    private function cartWith(array $products, array $headers = self::MOBILE): Cart
    {
        Cart::query()->where('customer_id', $this->customer->id)->each(fn (Cart $c) => $c->items()->delete() && $c->delete());
        $this->customer->refresh();
        $this->actingAs($this->customer, 'api')->withHeaders($headers)
            ->postJson('/api/v1/cart/init', ['store_id' => $this->store->id])->assertSuccessful();
        foreach ($products as [$product, $qty]) {
            $this->actingAs($this->customer, 'api')->withHeaders($headers)
                ->postJson('/api/v1/cart/items', [
                    'item_type' => 'product', 'product_id' => $product->id, 'quantity' => $qty,
                ])->assertSuccessful();
        }

        return Cart::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
    }

    private function complete(array $lines = null, string $platform = 'mobile'): Order
    {
        $lines ??= [['product_id' => $this->product(100)->id, 'quantity' => 1, 'price' => 100]];
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'received',
            'total' => 100,
            'platform' => $platform,
            'items_snapshot' => $lines,
        ]);
        $order->update(['status' => 'completed']);

        return $order;
    }

    private function card(): array
    {
        $this->customer->refresh();

        return [(int) $this->customer->cup_card_stamps, app(CupCardService::class)->effectiveStamps($this->customer)];
    }

    // ---- pricing ----

    public function test_early_slots_change_nothing_and_30_percent_equals_flat(): void
    {
        $p = $this->product(100);

        $this->stamps(0); // 0% slot
        $cart = $this->cartWith([[$p, 1]]);
        $this->assertEquals(30.0, (float) $cart->app_discount);
        $this->assertEquals(0.0, (float) $cart->cup_card_discount);
        $this->assertEquals(70.0, (float) $cart->total);

        $cart->update(['status' => 'abandoned']);
        $this->stamps(2); // 30% slot = flat 30%: priced exactly as before
        $cart = $this->cartWith([[$p, 1]]);
        $this->assertEquals(30.0, (float) $cart->app_discount);
        $this->assertEquals(0.0, (float) $cart->cup_card_discount);
        $this->assertEquals(70.0, (float) $cart->total);
        $this->assertFalse($cart->app_offer_snapshot['cup_card']['applied']);
    }

    public function test_50_percent_slot_discounts_only_the_most_expensive_cup(): void
    {
        $dear = $this->product(100);
        $cheap = $this->product(40);
        $this->stamps(4); // 50%
        $cart = $this->cartWith([[$cheap, 1], [$dear, 1]]);

        $this->assertEquals(50.0, (float) $cart->cup_card_discount);
        $this->assertEquals(12.0, (float) $cart->app_discount); // 30% of the other 40
        $this->assertEquals(62.0, (float) $cart->discount);
        $this->assertEquals(78.0, (float) $cart->total);
        $this->assertSame('app_offer', $cart->discount_source);
        $snap = $cart->app_offer_snapshot['cup_card'];
        $this->assertTrue($snap['applied']);
        $this->assertSame(4, $snap['stamps']);
        $this->assertSame($dear->id, $snap['product_id']);
        $this->assertEquals(100.0, $snap['unit_price']);
        $this->assertEquals(50.0, $snap['discount']);
        $this->assertSame($cart->items()->where('product_id', $dear->id)->value('id'), $snap['item_id']);
    }

    public function test_free_slot_makes_one_unit_free_and_flat_applies_to_the_rest(): void
    {
        $p = $this->product(100);
        $this->stamps(8); // 100%
        $cart = $this->cartWith([[$p, 2]]);

        $this->assertEquals(100.0, (float) $cart->cup_card_discount);
        $this->assertEquals(30.0, (float) $cart->app_discount); // 30% of the second cup
        $this->assertEquals(70.0, (float) $cart->total);
    }

    public function test_max_value_caps_the_card_discount(): void
    {
        Setting::set('cup_card.max_value', '40', 'string', 'cup_card');
        $this->stamps(8);
        $cart = $this->cartWith([[$this->product(100), 1]]);

        // Card 100% capped at 40 beats flat 30 on the cup; flat never applies on the card cup.
        $this->assertEquals(40.0, (float) $cart->cup_card_discount);
        $this->assertEquals(0.0, (float) $cart->app_discount);
        $this->assertEquals(60.0, (float) $cart->total);

        // A cap below the flat value on that cup means the card adds nothing.
        Setting::set('cup_card.max_value', '20', 'string', 'cup_card');
        $cart->recalculate();
        $cart->refresh();
        $this->assertEquals(0.0, (float) $cart->cup_card_discount);
        $this->assertEquals(30.0, (float) $cart->app_discount);
    }

    public function test_category_and_min_price_restrict_qualifying_cups(): void
    {
        $cake = $this->product(100, Category::factory()->create());
        Setting::set('cup_card.category_ids', [$this->coffee->id], 'json', 'cup_card');
        $this->stamps(8);
        $cart = $this->cartWith([[$cake, 1]]);
        $this->assertEquals(0.0, (float) $cart->cup_card_discount);
        $this->assertEquals(30.0, (float) $cart->app_discount);

        Setting::set('cup_card.min_price', '150', 'string', 'cup_card');
        $cart->items()->delete();
        $cart = $this->cartWith([[$this->product(100), 1]]);
        $this->assertEquals(0.0, (float) $cart->cup_card_discount);
    }

    public function test_wallet_gift_covers_a_different_unit_than_the_card_cup(): void
    {
        $p = $this->product(100);
        $this->stamps(8); // 100%
        $cart = $this->cartWith([[$p, 2]]);

        $gift = app(RewardIssuer::class)->issue(
            $this->customer, 'admin', 'free_item',
            ['category_ids' => [$this->coffee->id], 'max_value' => 130],
            ['en' => 'Free coffee'],
        );
        $this->actingAs($this->customer, 'api')->withHeaders(self::MOBILE)
            ->postJson('/api/v1/cart/apply-wallet-item', ['wallet_item_id' => $gift->id])->assertOk();
        $cart->refresh();

        // Gift: one cup. Card: the other cup. Nothing is discounted twice.
        $this->assertEquals(100.0, (float) $cart->wallet_discount);
        $this->assertEquals(100.0, (float) $cart->cup_card_discount);
        $this->assertEquals(0.0, (float) $cart->app_discount);
        $this->assertEquals(0.0, (float) $cart->total);

        // One cup only: the gift takes it, so the card has no unit.
        $cart->items()->update(['quantity' => 1]);
        $cart->recalculate();
        $cart->refresh();
        $this->assertEquals(100.0, (float) $cart->wallet_discount);
        $this->assertEquals(0.0, (float) $cart->cup_card_discount);
        $this->assertEquals(0.0, (float) $cart->total);
    }

    public function test_a_larger_promotion_wins_and_drops_the_card(): void
    {
        $this->stamps(4); // 50% => card candidate 50
        $cart = $this->cartWith([[$this->product(100), 1]]);
        $this->assertEquals(50.0, (float) $cart->cup_card_discount);

        PromoCode::factory()->create([
            'code' => 'BIG', 'discount_type' => 'percentage', 'discount_value' => 80,
            'minimum_order_amount' => 0, 'active' => true,
            'valid_from' => now()->subDay(), 'valid_to' => now()->addDay(),
        ]);
        $cart->update(['promo_code_id' => PromoCode::where('code', 'BIG')->value('id')]);
        $cart->recalculate();
        $cart->refresh();

        $this->assertSame('promotion', $cart->discount_source);
        $this->assertEquals(80.0, (float) $cart->promo_discount);
        $this->assertEquals(0.0, (float) $cart->cup_card_discount);
        $this->assertEquals(0.0, (float) $cart->app_discount);
        $this->assertFalse($cart->app_offer_snapshot['cup_card']['applied']);
        $this->assertEquals(20.0, (float) $cart->total);
    }

    public function test_non_app_platforms_and_disabled_card_get_nothing(): void
    {
        $p = $this->product(100);
        $this->stamps(8);
        $web = $this->cartWith([[$p, 1]], ['X-Kippis-Platform' => 'web']);
        $this->assertEquals(0.0, (float) $web->cup_card_discount);
        $this->assertEquals(100.0, (float) $web->total);

        $web->update(['status' => 'abandoned']);
        Setting::set('cup_card.enabled', false, 'boolean', 'cup_card');
        $cart = $this->cartWith([[$p, 1]]);
        $this->assertEquals(0.0, (float) $cart->cup_card_discount);
        $this->assertEquals(30.0, (float) $cart->app_discount);
    }

    public function test_cart_response_exposes_the_card_fields(): void
    {
        $this->stamps(4);
        $this->cartWith([[$this->product(100), 1]]);

        $this->actingAs($this->customer, 'api')->withHeaders(self::MOBILE)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.cup_card_discount', 50)
            ->assertJsonPath('data.app_offer_snapshot.cup_card.applied', true)
            ->assertJsonPath('data.app_offer_snapshot.cup_card.percent', 50)
            ->assertJsonStructure(['data' => ['app_offer_snapshot' => ['cup_card' => [
                'stamps', 'percent', 'applied', 'item_id', 'product_id', 'unit_price', 'discount',
            ]]]]);
    }

    public function test_checkout_copies_the_card_discount_to_the_order(): void
    {
        $payment = \App\Core\Models\PaymentMethod::create(['name' => 'Cash', 'code' => 'cash', 'is_active' => true]);
        $this->stamps(4);
        $this->cartWith([[$this->product(100), 1]]);

        $this->actingAs($this->customer, 'api')->withHeaders(self::MOBILE)
            ->postJson('/api/v1/orders/checkout', ['payment_method_id' => $payment->id])->assertSuccessful();

        $order = Order::where('customer_id', $this->customer->id)->firstOrFail();
        $this->assertEquals(50.0, (float) $order->cup_card_discount);
        $this->assertEquals(0.0, (float) $order->app_discount);
        $this->assertEquals(50.0, (float) $order->total);
        $this->assertSame(4, $order->app_offer_snapshot['cup_card']['stamps']);
    }

    // ---- stamping ----

    public function test_completed_app_order_stamps_once_regardless_of_cup_count(): void
    {
        Event::fake([CupCardStamped::class, CupCardCompleted::class]);
        $p = $this->product(100);
        $order = $this->complete([['product_id' => $p->id, 'quantity' => 3, 'price' => 100]]);

        $this->assertSame([1, 1], $this->card());
        Event::assertDispatched(CupCardStamped::class, fn ($e) => $e->stamps === 1 && $e->nextPercent === 0.0);

        // Re-running for the same order does not stamp again.
        app(CupCardService::class)->recordCompletedOrder($order);
        $this->assertSame([1, 1], $this->card());
        $this->assertSame(1, CupCardEvent::where('order_id', $order->id)->count());
    }

    public function test_no_stamp_for_non_app_disabled_or_non_qualifying_orders(): void
    {
        $this->complete(null, 'web');
        $this->assertSame([0, 0], $this->card());

        $cake = $this->product(100, Category::factory()->create());
        Setting::set('cup_card.category_ids', [$this->coffee->id], 'json', 'cup_card');
        $this->complete([['product_id' => $cake->id, 'quantity' => 1, 'price' => 100]]);
        $this->assertSame([0, 0], $this->card());

        Setting::set('cup_card.enabled', false, 'boolean', 'cup_card');
        $this->complete();
        $this->assertSame([0, 0], $this->card());
    }

    public function test_promo_order_still_stamps(): void
    {
        $order = $this->complete();
        $order->forceFill(['discount_source' => 'promotion', 'promo_discount' => 20])->save();
        $this->assertSame([1, 1], $this->card());
    }

    public function test_completing_the_card_resets_it(): void
    {
        Event::fake([CupCardStamped::class, CupCardCompleted::class]);
        $this->stamps(8); // the next order buys the last slot
        $order = $this->complete();

        $this->assertSame([0, 0], $this->card());
        Event::assertDispatched(CupCardCompleted::class);
        $this->assertSame(CupCardEvent::COMPLETE, CupCardEvent::where('order_id', $order->id)->value('type'));
    }

    public function test_card_expires_after_ttl_days_and_the_timer_restarts_on_a_stamp(): void
    {
        Carbon::setTestNow('2026-12-01 10:00:00');
        $this->complete();
        $this->complete();
        $this->assertSame([2, 2], $this->card());

        Carbon::setTestNow('2026-12-24 10:00:00'); // 23 days later: still alive
        $this->assertSame(2, $this->card()[1]);
        $this->assertSame(1, app(CupCardService::class)->status($this->customer)['days_left']);

        Carbon::setTestNow('2026-12-25 10:00:01'); // past 24 days: reset on read
        $this->assertSame([2, 0], $this->card());
        $status = app(CupCardService::class)->status($this->customer);
        $this->assertSame(0, $status['stamps']);
        $this->assertNull($status['expires_at']);

        // The next stamp starts a fresh card (expire event logged).
        $this->complete();
        $this->assertSame([1, 1], $this->card());
        $this->assertTrue(CupCardEvent::where('type', 'expire')->exists());
    }

    // ---- reversal ----

    public function test_cancel_after_completion_steps_back_and_is_idempotent(): void
    {
        $this->complete();
        $order = $this->complete();
        $this->assertSame([2, 2], $this->card());

        $order->update(['status' => 'cancelled']);
        $this->assertSame([1, 1], $this->card());

        app(CupCardService::class)->reverseOrder($order->fresh());
        $this->assertSame([1, 1], $this->card());
        $this->assertSame(1, CupCardEvent::where('order_id', $order->id)->where('type', 'unstamp')->count());
    }

    public function test_cancel_before_completion_does_nothing(): void
    {
        $this->stamps(3);
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id, 'status' => 'received', 'platform' => 'mobile',
        ]);
        $order->update(['status' => 'cancelled']);
        $this->assertSame([3, 3], $this->card());
    }

    public function test_step_back_floors_at_zero(): void
    {
        $order = $this->complete();
        $this->stamps(0);
        $order->update(['status' => 'cancelled']);
        $this->assertSame([0, 0], $this->card());
    }

    public function test_cancel_of_the_completing_order_restores_the_card(): void
    {
        $this->stamps(8);
        $order = $this->complete();
        $this->assertSame([0, 0], $this->card());

        $order->update(['status' => 'cancelled']);
        $this->assertSame([8, 8], $this->card());

        app(CupCardService::class)->reverseOrder($order->fresh());
        $this->assertSame([8, 8], $this->card());
        $this->assertSame(1, CupCardEvent::where('order_id', $order->id)->where('type', 'restore')->count());
    }

    public function test_restore_is_skipped_when_a_stamp_happened_since(): void
    {
        $this->stamps(8);
        $completing = $this->complete();
        $this->complete(); // first stamp of the new card
        $this->assertSame([1, 1], $this->card());

        $completing->update(['status' => 'cancelled']);
        $this->assertSame([0, 0], $this->card()); // plain step back, no restore
    }

    public function test_refund_reverses_the_stamp_once_fully_refunded(): void
    {
        $this->complete();
        $order = $this->complete();
        $this->assertSame([2, 2], $this->card());

        $admin = \App\Core\Models\Admin::factory()->create();
        $service = app(\App\Services\RefundService::class);
        $order->update(['payment_method' => 'cash']);
        $service->refundFull($order->fresh(), $admin, 'test');

        $this->assertSame([1, 1], $this->card());
        $this->assertSame(1, CupCardEvent::where('order_id', $order->id)->where('type', 'unstamp')->count());
    }

    // ---- endpoint ----

    public function test_get_endpoint_shape(): void
    {
        Carbon::setTestNow('2026-12-01 10:00:00');
        Setting::set('cup_card.category_ids', [$this->coffee->id], 'json', 'cup_card');
        $this->complete();
        $this->complete();
        $this->customer->refresh();

        $this->actingAs($this->customer, 'api')->getJson('/api/v1/cup-card')
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => [
                'enabled' => true,
                'steps' => [0, 0, 30, 40, 50, 60, 70, 80, 100],
                'stamps' => 2,
                'next_percent' => 30,
                'next_is_free' => false,
                'card_length' => 9,
                'expires_at' => '2026-12-25T12:00:00+02:00',
                'days_left' => 24,
                'category_ids' => [$this->coffee->id],
                'min_price' => 0,
                'max_value' => 0,
            ]]);

        $this->stamps(8);
        $this->customer->refresh();
        $this->actingAs($this->customer, 'api')->getJson('/api/v1/cup-card')
            ->assertJsonPath('data.next_percent', 100)
            ->assertJsonPath('data.next_is_free', true);
    }

    public function test_get_endpoint_is_disabled_by_default(): void
    {
        Setting::query()->where('key', 'cup_card.enabled')->delete();
        $this->stamps(5);
        $this->customer->refresh();

        $this->actingAs($this->customer, 'api')->getJson('/api/v1/cup-card')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.stamps', 0)
            ->assertJsonPath('data.expires_at', null)
            ->assertJsonPath('data.days_left', null)
            ->assertJsonPath('data.card_length', 9)
            ->assertJsonStructure(['data' => ['steps', 'next_percent', 'next_is_free', 'category_ids', 'min_price', 'max_value']]);
    }

    public function test_get_endpoint_requires_auth(): void
    {
        $this->getJson('/api/v1/cup-card')->assertUnauthorized();
    }
}
