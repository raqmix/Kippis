<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Core\Models\Product;
use App\Core\Models\Modifier;
use App\Core\Models\Cart;
use App\Core\Models\ProductModifierGroup;

class CartProductAddonsTest extends TestCase
{
    public function test_add_product_with_addons_to_cart_stores_addons_and_correct_price(): void
    {
        $product = Product::factory()->create(['base_price' => 15.00]);
        $modifier1 = Modifier::factory()->create(['price' => 2.00, 'max_level' => 5]);
        $modifier2 = Modifier::factory()->create(['price' => 1.50, 'max_level' => 3]);
        
        // Assign modifiers to product as addons
        ProductModifierGroup::create([
            'product_id' => $product->id,
            'modifier_id' => $modifier1->id,
            'is_required' => false,
            'min_select' => 0,
            'max_select' => 5,
        ]);
        
        ProductModifierGroup::create([
            'product_id' => $product->id,
            'modifier_id' => $modifier2->id,
            'is_required' => false,
            'min_select' => 0,
            'max_select' => 3,
        ]);

        $store = \App\Core\Models\Store::factory()->create();
        $customer = \App\Core\Models\Customer::factory()->create();

        // Init cart
        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/init', ['store_id' => $store->id]);

        // Add product with addons
        $payload = [
            'item_type' => 'product',
            'product_id' => $product->id,
            'quantity' => 1,
            'addons' => [
                ['modifier_id' => $modifier1->id, 'level' => 2],
                ['modifier_id' => $modifier2->id, 'level' => 1],
            ],
        ];

        $response = $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/items', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $cart = Cart::where('customer_id', $customer->id)->latest()->first();
        $item = $cart->items()->first();

        $this->assertEquals('product', $item->item_type);
        $this->assertEquals(20.00, (float)$item->price); // 15.00 + (2.00 * 2) + (1.50 * 1)
        $this->assertNotNull($item->configuration);
        $this->assertArrayHasKey('addons', $item->configuration);
        $this->assertCount(2, $item->configuration['addons']);
    }

    public function test_add_product_without_addons_works(): void
    {
        $product = Product::factory()->create(['base_price' => 20.00]);
        $store = \App\Core\Models\Store::factory()->create();
        $customer = \App\Core\Models\Customer::factory()->create();

        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/init', ['store_id' => $store->id]);

        $payload = [
            'item_type' => 'product',
            'product_id' => $product->id,
            'quantity' => 2,
        ];

        $response = $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/items', $payload);

        $response->assertStatus(201);
        
        $cart = Cart::where('customer_id', $customer->id)->latest()->first();
        $item = $cart->items()->first();

        $this->assertEquals(20.00, (float)$item->price);
        $this->assertEquals(2, $item->quantity);
    }

    public function test_add_product_with_invalid_addon_fails(): void
    {
        $product = Product::factory()->create(['base_price' => 15.00]);
        $modifier = Modifier::factory()->create(['price' => 2.00, 'max_level' => 5]);
        
        // Don't assign modifier to product

        $store = \App\Core\Models\Store::factory()->create();
        $customer = \App\Core\Models\Customer::factory()->create();

        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/init', ['store_id' => $store->id]);

        $payload = [
            'item_type' => 'product',
            'product_id' => $product->id,
            'quantity' => 1,
            'addons' => [
                ['modifier_id' => $modifier->id, 'level' => 1],
            ],
        ];

        $response = $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/items', $payload);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'INVALID_CONFIGURATION');
    }
}

