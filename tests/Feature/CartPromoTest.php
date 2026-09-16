<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Core\Models\Product;
use App\Core\Models\Cart;
use App\Core\Models\PromoCode;

class CartPromoTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_promo_code_updates_totals_correctly(): void
    {
        $product = Product::factory()->create(['base_price' => 50.00]);
        $store = \App\Core\Models\Store::factory()->create();
        $customer = \App\Core\Models\Customer::factory()->create();

        // Create promo code: 20% discount, minimum order 40.00
        $promoCode = PromoCode::factory()->create([
            'code' => 'SAVE20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'minimum_order_amount' => 40.00,
            'active' => true,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addDay(),
        ]);

        // Init cart and add product
        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/init', ['store_id' => $store->id]);

        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/items', [
                'item_type' => 'product',
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        // Apply promo code
        $response = $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/apply-promo', ['code' => 'SAVE20']);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $cart = Cart::where('customer_id', $customer->id)->latest()->first();
        $cart->load('items', 'promoCode');
        $cart->recalculate();

        $this->assertEquals(50.00, (float)$cart->subtotal);
        $this->assertEquals(10.00, (float)$cart->discount); // 20% of 50.00
        $this->assertEquals(40.00, (float)$cart->total); // 50.00 - 10.00
    }

    public function test_cart_totals_sum_stored_prices_no_repricing(): void
    {
        $product1 = Product::factory()->create(['base_price' => 25.00]);
        $product2 = Product::factory()->create(['base_price' => 15.00]);
        $store = \App\Core\Models\Store::factory()->create();
        $customer = \App\Core\Models\Customer::factory()->create();

        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/init', ['store_id' => $store->id]);

        // Add two products
        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/items', [
                'item_type' => 'product',
                'product_id' => $product1->id,
                'quantity' => 1,
            ]);

        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/items', [
                'item_type' => 'product',
                'product_id' => $product2->id,
                'quantity' => 2,
            ]);

        $cart = Cart::where('customer_id', $customer->id)->latest()->first();
        $cart->load('items');
        $cart->recalculate();

        // Total should be sum of stored prices: 25.00 + (15.00 * 2) = 55.00
        $this->assertEquals(55.00, (float)$cart->subtotal);
        $this->assertEquals(55.00, (float)$cart->total);

        // Verify prices are stored (not recalculated)
        $items = $cart->items;
        $this->assertEquals(25.00, (float)$items[0]->price);
        $this->assertEquals(15.00, (float)$items[1]->price);
    }

    public function test_remove_promo_code_updates_totals(): void
    {
        $product = Product::factory()->create(['base_price' => 50.00]);
        $store = \App\Core\Models\Store::factory()->create();
        $customer = \App\Core\Models\Customer::factory()->create();

        $promoCode = PromoCode::factory()->create([
            'code' => 'SAVE10',
            'discount_type' => 'fixed',
            'discount_value' => 10.00,
            'minimum_order_amount' => 40.00,
            'active' => true,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addDay(),
        ]);

        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/init', ['store_id' => $store->id]);

        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/items', [
                'item_type' => 'product',
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        // Apply promo
        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/apply-promo', ['code' => 'SAVE10']);

        // Remove promo
        $response = $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/remove-promo');

        $response->assertStatus(200);

        $cart = Cart::where('customer_id', $customer->id)->latest()->first();
        $cart->load('items', 'promoCode');
        $cart->recalculate();

        $this->assertNull($cart->promo_code_id);
        $this->assertEquals(0.00, (float)$cart->discount);
        $this->assertEquals(50.00, (float)$cart->total);
    }

    /**
     * Regression: recalculate() must discount against the promo currently on
     * the row, not a promoCode relation a caller loaded earlier. Callers such
     * as CartController::sync() load promoCode, then change promo_code_id; a
     * plain load() skips an already-loaded relation, so the cart used to be
     * priced against the stale promo (or against none at all, leaving the
     * promo visibly applied with a zero discount).
     */
    public function test_recalculate_uses_current_promo_not_stale_relation(): void
    {
        $product = Product::factory()->create(['base_price' => 200.00]);
        $store = \App\Core\Models\Store::factory()->create();
        $customer = \App\Core\Models\Customer::factory()->create();

        $promoCode = PromoCode::factory()->create([
            'code' => 'PCT15',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'minimum_order_amount' => 0.00,
            'active' => true,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addDay(),
        ]);

        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/init', ['store_id' => $store->id]);

        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/items', [
                'item_type' => 'product',
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $cart = Cart::where('customer_id', $customer->id)->latest()->first();

        // Mirror the sync() path: promoCode is loaded (as null) *before*
        // promo_code_id is set, so the relation is stale by the time
        // recalculate() runs.
        $cart->load('promoCode');
        $this->assertNull($cart->promoCode);

        $cart->update(['promo_code_id' => $promoCode->id]);
        $cart->recalculate();

        $this->assertEquals(200.00, (float)$cart->subtotal);
        $this->assertEquals(30.00, (float)$cart->discount); // 15% of 200.00
        $this->assertEquals(170.00, (float)$cart->total);
    }
}

