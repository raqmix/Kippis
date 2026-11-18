<?php

namespace Tests\Feature;

use App\Core\Models\Order;
use App\Core\Models\PaymentMethod;
use App\Core\Models\Product;
use App\Core\Models\Store;
use App\Core\Repositories\OrderRepository;
use App\Integrations\Foodics\Exceptions\FoodicsMaintenanceException;
use App\Integrations\Foodics\Exceptions\FoodicsRateLimitException;
use App\Integrations\Foodics\Exceptions\FoodicsTimeoutException;
use App\Integrations\Foodics\Services\FoodicsAuthService;
use App\Integrations\Foodics\Services\FoodicsClient;
use App\Integrations\Foodics\Services\FoodicsRateLimiter;
use App\Jobs\PushOrderToFoodics;
use App\Services\MixPriceCalculator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kiosk checkout must answer inside the kiosk's 30s HTTP timeout, and a
 * retry after a timeout must not create a second order.
 *
 * B1: the inline Foodics push used to wait on the shared rate limiter (up
 * to 65s) and sleep through 429 retries (up to 20s each) inside the
 * request. Now it goes only if it can go at once, within a fixed budget,
 * and otherwise leaves the push to the queue.
 *
 * Idempotency-Key: same key + same store within 24h returns the original
 * order (same 201 body) without creating or pushing another.
 */
class KioskCheckoutSafetyTest extends TestCase
{
    use RefreshDatabase;

    /** Plain kiosk API key per store id (the column is unique). */
    private array $apiKeys = [];

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['foodics.mode' => 'live']);

        PaymentMethod::create(['name' => 'Card', 'code' => 'card', 'is_active' => true]);

        $this->store = $this->makeStore();
        $this->product = Product::factory()->foodics()->create(['base_price' => 40.00, 'is_active' => true]);

        // No network: the token lookup is stubbed and every Foodics call
        // goes through Http::fake() set per test.
        $auth = $this->createMock(FoodicsAuthService::class);
        $auth->method('getAccessToken')->willReturn('test-token');
        $this->app->instance(FoodicsAuthService::class, $auth);
    }

    private function makeStore(): Store
    {
        $apiKey = 'test-kiosk-key-' . Str::random(8);
        $store = Store::factory()->create([
            'is_active' => true,
            'receive_online_orders' => true,
            'kiosk_api_key' => hash('sha256', $apiKey),
            'foodics_branch_id' => 'br-' . Str::random(6),
        ]);
        $this->apiKeys[$store->id] = $apiKey;

        return $store;
    }

    private function checkout(?string $key = null, ?Store $store = null)
    {
        $store ??= $this->store;
        $headers = [
            'X-Store-ID' => (string) $store->id,
            'X-Kiosk-API-Key' => $this->apiKeys[$store->id],
        ];
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }

        // Per-call headers: withHeaders() would stick to later requests.
        return $this->postJson('/api/v1/kiosk/orders/checkout', [
            'payment_method' => 'card',
            'items' => [[
                'product_id' => $this->product->id,
                'item_type' => 'product',
                'name' => 'Drink',
                'quantity' => 1,
            ]],
        ], $headers);
    }

    private function fakeFoodicsSuccess(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 'fd-order-1', 'check_number' => 4242]], 200)]);
    }

    /** A limiter with no budget left this window, which must never be waited on. */
    private function exhaustedLimiter(): void
    {
        $limiter = $this->createMock(FoodicsRateLimiter::class);
        $limiter->method('tryAcquire')->willReturn(false);
        $limiter->expects($this->never())->method('acquire');
        $this->app->instance(FoodicsRateLimiter::class, $limiter);
    }

    private function sentCount(): int
    {
        return Http::recorded()->count();
    }

    // ---------------------------------------------------------------------
    // B1: inline push never blocks the request
    // ---------------------------------------------------------------------

    public function test_inline_push_success_returns_foodics_reference(): void
    {
        Queue::fake();
        $this->fakeFoodicsSuccess();

        $res = $this->checkout()->assertStatus(201);

        $this->assertSame('fd-order-1', $res->json('data.foodics_order_id'));
        $this->assertSame(1, $this->sentCount());
        // Only the OrderCreated listener's job; no fallback needed.
        Queue::assertPushed(PushOrderToFoodics::class, 1);
    }

    public function test_exhausted_rate_limiter_falls_back_to_queue_without_waiting(): void
    {
        Queue::fake();
        Http::fake();
        $this->exhaustedLimiter();

        $start = microtime(true);
        $res = $this->checkout()->assertStatus(201);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(5, $elapsed, 'checkout must not wait on the Foodics limiter');
        $this->assertSame(0, $this->sentCount());
        $this->assertNull($res->json('data.foodics_order_id'));
        $this->assertDatabaseCount('orders', 1);

        // Listener job + the safety-net fallback job, both for this order.
        Queue::assertPushed(PushOrderToFoodics::class, 2);
        Queue::assertPushed(PushOrderToFoodics::class, fn ($job) => $job->orderId === $res->json('data.order_id') && $job->delay !== null);
    }

    public function test_foodics_429_is_not_retried_inline(): void
    {
        Queue::fake();
        // retry-after 10 is within what the patient client would sleep for.
        Http::fake(['*' => Http::response(['error' => 'rate limited'], 429, ['retry-after' => '10'])]);

        $start = microtime(true);
        $res = $this->checkout()->assertStatus(201);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(5, $elapsed, 'inline push must not sleep on Retry-After');
        $this->assertSame(1, $this->sentCount());
        $this->assertNull($res->json('data.foodics_order_id'));
        Queue::assertPushed(PushOrderToFoodics::class, 2);
    }

    public function test_spent_budget_skips_the_call_and_falls_back(): void
    {
        Queue::fake();
        Http::fake();
        config(['foodics.kiosk_inline_timeout' => 0]);

        $this->checkout()->assertStatus(201);

        $this->assertSame(0, $this->sentCount());
        Queue::assertPushed(PushOrderToFoodics::class, 2);
    }

    public function test_fail_fast_client_unit_behaviour(): void
    {
        // 503 is not retried with sleeps in fail-fast mode.
        Http::fake(['*' => Http::response([], 503)]);
        $start = microtime(true);
        try {
            app(FoodicsClient::class)->withDeadline(8)->post('v5/orders', []);
            $this->fail('expected FoodicsMaintenanceException');
        } catch (FoodicsMaintenanceException) {
        }
        $this->assertLessThan(2, microtime(true) - $start);
        $this->assertSame(1, $this->sentCount());

        // Expired budget: no request at all.
        $this->expectException(FoodicsTimeoutException::class);
        app(FoodicsClient::class)->withDeadline(0)->get('v5/branches');
    }

    public function test_fail_fast_client_does_not_wait_for_limiter(): void
    {
        Http::fake();
        $this->exhaustedLimiter();

        $start = microtime(true);
        try {
            app(FoodicsClient::class)->withDeadline(8)->get('v5/branches');
            $this->fail('expected FoodicsRateLimitException');
        } catch (FoodicsRateLimitException) {
        }
        $this->assertLessThan(1, microtime(true) - $start);
        $this->assertSame(0, $this->sentCount());
    }

    // ---------------------------------------------------------------------
    // Idempotency-Key
    // ---------------------------------------------------------------------

    public function test_same_key_replays_original_order_without_new_order_or_push(): void
    {
        Queue::fake();
        $this->fakeFoodicsSuccess();
        $key = (string) Str::uuid();

        $first = $this->checkout($key)->assertStatus(201);
        $second = $this->checkout($key)->assertStatus(201);

        $this->assertSame($first->json(), $second->json());
        $second->assertHeader('Idempotent-Replayed', 'true');
        $this->assertFalse($first->headers->has('Idempotent-Replayed'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, $this->sentCount(), 'replay must not push to Foodics again');
        Queue::assertPushed(PushOrderToFoodics::class, 1);
    }

    public function test_replay_wins_even_if_catalog_changed_since(): void
    {
        Queue::fake();
        $this->fakeFoodicsSuccess();
        $key = (string) Str::uuid();

        $first = $this->checkout($key)->assertStatus(201);
        $this->product->update(['is_active' => false]);

        $this->checkout($key)->assertStatus(201)
            ->assertJsonPath('data.order_id', $first->json('data.order_id'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_key_is_scoped_per_store_and_missing_key_keeps_old_behaviour(): void
    {
        Queue::fake();
        $this->fakeFoodicsSuccess();
        $key = (string) Str::uuid();
        $other = $this->makeStore();

        $a = $this->checkout($key)->assertStatus(201);
        $b = $this->checkout($key, $other)->assertStatus(201);
        $this->assertNotSame($a->json('data.order_id'), $b->json('data.order_id'));

        $this->checkout()->assertStatus(201);
        $this->checkout()->assertStatus(201);
        $this->assertDatabaseCount('orders', 4);
    }

    public function test_key_older_than_24h_is_released_and_creates_new_order(): void
    {
        Queue::fake();
        $this->fakeFoodicsSuccess();
        $key = (string) Str::uuid();

        $first = $this->checkout($key)->assertStatus(201);
        Order::whereKey($first->json('data.order_id'))->update(['created_at' => now()->subHours(25)]);

        $second = $this->checkout($key)->assertStatus(201);

        $this->assertNotSame($first->json('data.order_id'), $second->json('data.order_id'));
        $this->assertNull(Order::find($first->json('data.order_id'))->idempotency_key);
        $this->assertSame($key, Order::find($second->json('data.order_id'))->idempotency_key);
    }

    public function test_overlong_key_is_rejected(): void
    {
        $this->checkout(str_repeat('k', 101))->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unique_index_blocks_duplicate_key_per_store(): void
    {
        Queue::fake();
        $this->fakeFoodicsSuccess();
        $key = (string) Str::uuid();
        $this->checkout($key)->assertStatus(201);
        $order = Order::first();

        $this->expectException(UniqueConstraintViolationException::class);
        Order::create(array_merge(
            $order->only(['store_id', 'status', 'total', 'subtotal', 'discount', 'payment_method', 'payment_method_id', 'platform']),
            ['pickup_code' => 'DUP' . random_int(100, 999), 'idempotency_key' => $key, 'items_snapshot' => []]
        ));
    }

    /**
     * Two requests with the same key both miss the lookup; the second one's
     * insert hits the unique index. It must answer with the first one's
     * order, not a 500 and not a second order.
     */
    public function test_concurrent_duplicate_loses_insert_and_replays_winner(): void
    {
        Queue::fake();
        $this->fakeFoodicsSuccess();
        $key = (string) Str::uuid();

        // Simulate the other request committing between our lookup and our
        // insert: create its order just before ours, then let ours run.
        $this->app->instance(OrderRepository::class, new class(app(MixPriceCalculator::class)) extends OrderRepository {
            public ?int $winnerId = null;

            public function createFromItems(int $storeId, array $items, string $paymentMethodCode, ?string $promoCode = null, ?string $posCode = null, ?string $idempotencyKey = null): Order
            {
                $winner = parent::createFromItems($storeId, $items, $paymentMethodCode, $promoCode, $posCode, $idempotencyKey);
                $this->winnerId = $winner->id;

                return parent::createFromItems($storeId, $items, $paymentMethodCode, $promoCode, $posCode, $idempotencyKey);
            }
        });

        $res = $this->checkout($key)->assertStatus(201);
        $res->assertHeader('Idempotent-Replayed', 'true');

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(app(OrderRepository::class)->winnerId, $res->json('data.order_id'));
    }
}
