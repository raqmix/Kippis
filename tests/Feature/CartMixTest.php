<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Core\Models\Product;
use App\Core\Models\Modifier;
use App\Core\Models\Cart;

class CartMixTest extends TestCase
{
    public function test_add_mix_to_cart_saves_snapshot_and_price(): void
    {
        $product = Product::factory()->create(['base_price' => 12.00]);
        $modifier = Modifier::factory()->create(['price' => 1.50, 'max_level' => 5]);
        $store = \App\Core\Models\Store::factory()->create();
        $customer = \App\Core\Models\Customer::factory()->create();

        // init cart
        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/init', ['store_id' => $store->id]);

        $configuration = ['base_id' => $product->id, 'modifiers' => [['id' => $modifier->id, 'level' => 1]]];

        $response = $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/items', ['item_type' => 'mix', 'quantity' => 1, 'configuration' => $configuration]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $cart = Cart::where('customer_id', $customer->id)->latest()->first();
        $this->assertNotNull($cart);
        $this->assertGreaterThan(0, $cart->items()->count());

        $item = $cart->items()->first();
        $this->assertEquals('mix', $item->item_type);
        $this->assertNotNull($item->configuration);
        $this->assertEquals(13.50, (float)$item->price); // 12.00 + (1.50 * 1)
        
        // Verify configuration snapshot is stored
        $this->assertEquals($product->id, $item->configuration['base_id']);
        $this->assertArrayHasKey('modifiers', $item->configuration);
    }

    public function test_add_creator_mix_to_cart(): void
    {
        $product = Product::factory()->create(['base_price' => 15.00]);
        $modifier = Modifier::factory()->create(['price' => 2.00, 'max_level' => 5]);
        $extraProduct = Product::factory()->create(['base_price' => 5.00]);
        $store = \App\Core\Models\Store::factory()->create();
        $customer = \App\Core\Models\Customer::factory()->create();

        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/init', ['store_id' => $store->id]);

        $configuration = [
            'base_id' => $product->id,
            'modifiers' => [['id' => $modifier->id, 'level' => 2]],
            'extras' => [$extraProduct->id],
        ];

        $response = $this->actingAs($customer, 'api')
            ->postJson('/api/v1/cart/items', [
                'item_type' => 'creator_mix',
                'quantity' => 1,
                'configuration' => $configuration,
                'ref_id' => 10,
                'name' => 'Berry Blast Mix',
            ]);

        $response->assertStatus(201);

        $cart = Cart::where('customer_id', $customer->id)->latest()->first();
        $item = $cart->items()->first();

        $this->assertEquals('creator_mix', $item->item_type);
        $this->assertEquals(10, $item->ref_id);
        $this->assertEquals('Berry Blast Mix', $item->name);
        $this->assertEquals(24.00, (float)$item->price); // 15.00 + (2.00 * 2) + 5.00
    }
}
