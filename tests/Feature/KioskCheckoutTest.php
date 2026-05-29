<?php

namespace Tests\Feature;

use App\Core\Models\PaymentMethod;
use App\Core\Models\Product;
use App\Core\Models\PromoCode;
use App\Core\Models\Store;
use App\Core\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KioskCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function paymentMethods(): void
    {
        PaymentMethod::create(['name' => 'Cash', 'code' => 'cash', 'is_active' => true]);
        PaymentMethod::create(['name' => 'Card', 'code' => 'card', 'is_active' => true]);
    }

    private string $apiKey = 'test-kiosk-key';

    private function kioskStore(): Store
    {
        return Store::factory()->create([
            'is_active' => true,
            'receive_online_orders' => true,
            'kiosk_api_key' => hash('sha256', $this->apiKey),
        ]);
    }

    private function headers(Store $store): array
    {
        return [
            'X-Store-ID' => (string) $store->id,
            'X-Kiosk-API-Key' => $this->apiKey,
        ];
    }

    public function test_checkout_ignores_client_price_and_uses_catalog_price(): void
    {
        $this->paymentMethods();
        $store = $this->kioskStore();
        $product = Product::factory()->create(['base_price' => 50.00, 'is_active' => true]);

        // Client tries to pay 1.00 for a 50.00 product.
        $response = $this->withHeaders($this->headers($store))
            ->postJson('/api/v1/kiosk/orders/checkout', [
                'payment_method' => 'card',
                'items' => [[
                    'product_id' => $product->id,
                    'item_type' => 'product',
                    'name' => 'Tampered',
                    'quantity' => 1,
                    'price' => 1.00,
                ]],
                'subtotal' => 1.00,
                'discount' => 0,
                'total' => 1.00,
            ]);

        $response->assertStatus(201);
        $this->assertEquals(50.00, (float) $response->json('data.total'));

        $order = Order::find($response->json('data.order_id'));
        $this->assertEquals(50.00, (float) $order->subtotal);
        $this->assertEquals(50.00, (float) $order->total);
        $this->assertEquals(50.00, (float) $order->items_snapshot[0]['price']);
    }

    public function test_checkout_respects_quantity_in_recomputed_subtotal(): void
    {
        $this->paymentMethods();
        $store = $this->kioskStore();
        $product = Product::factory()->create(['base_price' => 20.00, 'is_active' => true]);

        $response = $this->withHeaders($this->headers($store))
            ->postJson('/api/v1/kiosk/orders/checkout', [
                'payment_method' => 'card',
                'items' => [[
                    'product_id' => $product->id,
                    'item_type' => 'product',
                    'name' => 'Drink',
                    'quantity' => 3,
                    'price' => 0,
                ]],
            ]);

        $response->assertStatus(201);
        $this->assertEquals(60.00, (float) $response->json('data.total'));
    }

    public function test_checkout_recomputes_promo_discount_and_ignores_client_discount(): void
    {
        $this->paymentMethods();
        $store = $this->kioskStore();
        $product = Product::factory()->create(['base_price' => 100.00, 'is_active' => true]);

        PromoCode::factory()->create([
            'code' => 'SAVE20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'minimum_order_amount' => 40.00,
            'active' => true,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addDay(),
        ]);

        // Client claims a 99.00 discount; server should compute 20.00.
        $response = $this->withHeaders($this->headers($store))
            ->postJson('/api/v1/kiosk/orders/checkout', [
                'payment_method' => 'card',
                'items' => [[
                    'product_id' => $product->id,
                    'item_type' => 'product',
                    'name' => 'Drink',
                    'quantity' => 1,
                    'price' => 100.00,
                ]],
                'discount' => 99.00,
                'total' => 1.00,
                'promo_code' => 'SAVE20',
            ]);

        $response->assertStatus(201);
        $order = Order::find($response->json('data.order_id'));
        $this->assertEquals(100.00, (float) $order->subtotal);
        $this->assertEquals(20.00, (float) $order->discount);
        $this->assertEquals(80.00, (float) $order->total);
    }

    public function test_checkout_ignores_invalid_promo_code(): void
    {
        $this->paymentMethods();
        $store = $this->kioskStore();
        $product = Product::factory()->create(['base_price' => 30.00, 'is_active' => true]);

        $response = $this->withHeaders($this->headers($store))
            ->postJson('/api/v1/kiosk/orders/checkout', [
                'payment_method' => 'card',
                'items' => [[
                    'product_id' => $product->id,
                    'item_type' => 'product',
                    'name' => 'Drink',
                    'quantity' => 1,
                ]],
                'promo_code' => 'NOPE',
            ]);

        $response->assertStatus(201);
        $order = Order::find($response->json('data.order_id'));
        $this->assertEquals(0.00, (float) $order->discount);
        $this->assertEquals(30.00, (float) $order->total);
        $this->assertNull($order->promo_code_id);
    }

    public function test_checkout_rejects_inactive_product(): void
    {
        $this->paymentMethods();
        $store = $this->kioskStore();
        $product = Product::factory()->create(['base_price' => 30.00, 'is_active' => false]);

        $response = $this->withHeaders($this->headers($store))
            ->postJson('/api/v1/kiosk/orders/checkout', [
                'payment_method' => 'card',
                'items' => [[
                    'product_id' => $product->id,
                    'item_type' => 'product',
                    'name' => 'Drink',
                    'quantity' => 1,
                ]],
            ]);

        $response->assertStatus(400);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cash_payment_requires_pos_code(): void
    {
        $this->paymentMethods();
        $store = $this->kioskStore();
        $product = Product::factory()->create(['base_price' => 30.00, 'is_active' => true]);

        $response = $this->withHeaders($this->headers($store))
            ->postJson('/api/v1/kiosk/orders/checkout', [
                'payment_method' => 'cash',
                'items' => [[
                    'product_id' => $product->id,
                    'item_type' => 'product',
                    'name' => 'Drink',
                    'quantity' => 1,
                ]],
            ]);

        $response->assertStatus(422);
    }

    public function test_checkout_requires_auth_headers(): void
    {
        $this->paymentMethods();
        $product = Product::factory()->create(['base_price' => 30.00, 'is_active' => true]);

        $response = $this->postJson('/api/v1/kiosk/orders/checkout', [
            'payment_method' => 'card',
            'items' => [[
                'product_id' => $product->id,
                'item_type' => 'product',
                'name' => 'Drink',
                'quantity' => 1,
            ]],
        ]);

        $response->assertStatus(401);
    }

    public function test_checkout_rejects_invalid_api_key(): void
    {
        $this->paymentMethods();
        $store = $this->kioskStore();
        $product = Product::factory()->create(['base_price' => 30.00, 'is_active' => true]);

        $response = $this->withHeaders([
            'X-Store-ID' => (string) $store->id,
            'X-Kiosk-API-Key' => 'wrong-key',
        ])->postJson('/api/v1/kiosk/orders/checkout', [
            'payment_method' => 'card',
            'items' => [[
                'product_id' => $product->id,
                'item_type' => 'product',
                'name' => 'Drink',
                'quantity' => 1,
            ]],
        ]);

        $response->assertStatus(401);
    }
}
