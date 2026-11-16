<?php

namespace Tests\Feature;

use App\Core\Models\Category;
use App\Core\Models\Customer;
use App\Core\Models\LoyaltyTransaction;
use App\Core\Models\LoyaltyWallet;
use App\Core\Models\Product;
use App\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pay with points: the balance check used a non-existent `balance` attribute, so every apply failed. */
class ApplyPointsDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function customerWithCart(int $points): Customer
    {
        $customer = Customer::factory()->create();
        $wallet = LoyaltyWallet::factory()->create(['customer_id' => $customer->id]);
        if ($points > 0) {
            $wallet->addPoints($points, LoyaltyTransaction::TYPE_EARNED, 'seed');
        }
        $store = Store::factory()->create(['is_active' => true, 'receive_online_orders' => true]);
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'base_price' => 100,
        ]);
        $this->actingAs($customer, 'api')->withHeaders(['X-Kippis-Platform' => 'ios'])
            ->postJson('/api/v1/cart/items', ['item_type' => 'product', 'product_id' => $product->id, 'quantity' => 1, 'store_id' => $store->id])
            ->assertStatus(201);

        return $customer;
    }

    public function test_applies_points_within_balance(): void
    {
        $customer = $this->customerWithCart(50);

        $this->actingAs($customer, 'api')->postJson('/api/v1/cart/apply-points-discount', ['points' => 20])
            ->assertOk()->assertJsonPath('success', true);
    }

    public function test_rejects_points_above_balance(): void
    {
        $customer = $this->customerWithCart(10);

        $this->actingAs($customer, 'api')->postJson('/api/v1/cart/apply-points-discount', ['points' => 20])
            ->assertStatus(422)->assertJsonPath('error.code', 'INSUFFICIENT_POINTS');
    }
}
