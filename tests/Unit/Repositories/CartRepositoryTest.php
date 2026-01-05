<?php

namespace Tests\Unit\Repositories;

use App\Core\Models\Cart;
use App\Core\Models\Customer;
use App\Core\Models\Product;
use App\Core\Models\Store;
use App\Core\Repositories\CartRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CartRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CartRepository();
    }

    public function test_can_create_cart(): void
    {
        $customer = Customer::factory()->create();
        $store = Store::factory()->create();

        $cart = $this->repository->create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
        ]);

        $this->assertNotNull($cart);
        $this->assertEquals($customer->id, $cart->customer_id);
        $this->assertEquals($store->id, $cart->store_id);
    }

    public function test_can_find_active_cart(): void
    {
        $customer = Customer::factory()->create();
        $store = Store::factory()->create();

        $cart = Cart::factory()->create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'abandoned_at' => null,
        ]);

        $found = $this->repository->findActiveCart($customer->id);

        $this->assertNotNull($found);
        $this->assertEquals($cart->id, $found->id);
    }

    public function test_can_add_item_to_cart(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['base_price' => 25.00]);

        $cartItem = $this->repository->addItem($cart, $product->id, 2, ['sweetness' => 3]);

        $this->assertNotNull($cartItem);
        $this->assertEquals($cart->id, $cartItem->cart_id);
        $this->assertEquals($product->id, $cartItem->product_id);
        $this->assertEquals(2, $cartItem->quantity);
    }
}

