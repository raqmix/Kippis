<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\Customer;
use App\Core\Models\Product;
use App\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartControllerTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::factory()->create();
    }

    public function test_can_init_cart(): void
    {
        $store = Store::factory()->create();

        $response = $this->actingAs($this->customer, 'api')
            ->postJson('/api/v1/cart/init', [
                'store_id' => $store->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['cart_id'],
            ]);
    }

    public function test_can_add_item_to_cart(): void
    {
        $store = Store::factory()->create();
        $product = Product::factory()->create();

        // Init cart
        $this->actingAs($this->customer, 'api')
            ->postJson('/api/v1/cart/init', ['store_id' => $store->id]);

        // Add item
        $response = $this->actingAs($this->customer, 'api')
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 2,
                'modifiers' => ['sweetness' => 3],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_requires_authentication_for_cart_operations(): void
    {
        $store = Store::factory()->create();

        $response = $this->postJson('/api/v1/cart/init', [
            'store_id' => $store->id,
        ]);

        // The customer cart endpoints are behind auth:api; guests are rejected.
        $response->assertStatus(401);
    }
}

