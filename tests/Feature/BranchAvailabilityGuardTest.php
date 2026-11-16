<?php

namespace Tests\Feature;

use App\Core\Models\Customer;
use App\Core\Models\PaymentMethod;
use App\Core\Models\Product;
use App\Core\Models\SquadCartItem;
use App\Core\Models\SquadMember;
use App\Core\Models\SquadPayment;
use App\Core\Models\SquadSession;
use App\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Items switched off at a branch (product_store.is_available = false) must
 * not get through kiosk or squad checkout, same as the app's own checkout.
 */
class BranchAvailabilityGuardTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = 'test-kiosk-key';

    private function kioskStore(): Store
    {
        PaymentMethod::create(['name' => 'Cash', 'code' => 'cash', 'is_active' => true]);
        PaymentMethod::create(['name' => 'Card', 'code' => 'card', 'is_active' => true]);

        return Store::factory()->create([
            'is_active' => true,
            'receive_online_orders' => true,
            'kiosk_api_key' => hash('sha256', $this->apiKey),
        ]);
    }

    private function kioskCheckout(Store $store, Product $product)
    {
        return $this->withHeaders([
            'X-Store-ID' => (string) $store->id,
            'X-Kiosk-API-Key' => $this->apiKey,
        ])->postJson('/api/v1/kiosk/orders/checkout', [
            'payment_method' => 'card',
            'items' => [[
                'product_id' => $product->id,
                'item_type' => 'product',
                'name' => 'Iced Latte',
                'quantity' => 1,
            ]],
        ]);
    }

    public function test_kiosk_checkout_rejects_item_switched_off_at_branch(): void
    {
        $store = $this->kioskStore();
        $product = Product::factory()->create(['base_price' => 50.00]);
        $product->stores()->attach($store->id, ['is_available' => false]);

        $response = $this->kioskCheckout($store, $product);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'ITEMS_UNAVAILABLE')
            ->assertJsonPath('error.product_ids', [$product->id]);
        $this->assertStringContainsString('Iced Latte', $response->json('error.message'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_kiosk_checkout_rejects_item_only_on_another_branch(): void
    {
        $store = $this->kioskStore();
        $other = Store::factory()->create();
        $product = Product::factory()->create(['base_price' => 50.00]);
        $product->stores()->attach($other->id, ['is_available' => true]);

        $this->kioskCheckout($store, $product)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ITEMS_UNAVAILABLE');
    }

    public function test_kiosk_checkout_allows_item_available_at_branch(): void
    {
        $store = $this->kioskStore();
        $product = Product::factory()->create(['base_price' => 50.00]);
        $product->stores()->attach($store->id, ['is_available' => true]);

        $this->kioskCheckout($store, $product)->assertStatus(201);
    }

    private function squadWithItem(Store $store, Product $product): array
    {
        $host = Customer::factory()->create();
        $session = SquadSession::create([
            'host_id' => $host->id,
            'store_id' => $store->id,
            'invite_code' => 'ABC123',
            'status' => 'open',
            'expires_at' => now()->addHour(),
        ]);
        $member = SquadMember::create([
            'squad_session_id' => $session->id,
            'customer_id' => $host->id,
            'nickname' => 'Host',
            'joined_at' => now(),
            'is_host' => true,
        ]);
        SquadCartItem::create([
            'squad_session_id' => $session->id,
            'squad_member_id' => $member->id,
            'product_id' => $product->id,
            'product_kind' => 'standard',
            'quantity' => 1,
            'unit_price' => 5000,
        ]);

        return [$host, $session];
    }

    public function test_squad_checkout_rejects_item_switched_off_at_branch(): void
    {
        $store = Store::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['base_price' => 50.00]);
        $product->stores()->attach($store->id, ['is_available' => false]);
        [$host, $session] = $this->squadWithItem($store, $product);

        $this->actingAs($host, 'api')
            ->postJson("/api/v1/squad/{$session->id}/checkout", ['store_id' => $store->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ITEMS_UNAVAILABLE')
            ->assertJsonPath('error.product_ids', [$product->id]);

        $this->assertSame('open', $session->fresh()->status);
        $this->assertSame(0, SquadPayment::count());
    }

    public function test_squad_checkout_by_non_host_does_not_reveal_cart(): void
    {
        $store = Store::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['base_price' => 50.00]);
        $product->stores()->attach($store->id, ['is_available' => false]);
        [, $session] = $this->squadWithItem($store, $product);
        $stranger = Customer::factory()->create();

        $res = $this->actingAs($stranger, 'api')
            ->postJson("/api/v1/squad/{$session->id}/checkout", ['store_id' => $store->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SQUAD_ERROR');
        $this->assertNull($res->json('error.product_ids'));
    }

    public function test_squad_checkout_guard_uses_session_store(): void
    {
        $sessionStore = Store::factory()->create(['is_active' => true]);
        $other = Store::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['base_price' => 50.00]);
        $product->stores()->attach($sessionStore->id, ['is_available' => false]);
        $product->stores()->attach($other->id, ['is_available' => true]);
        [$host, $session] = $this->squadWithItem($sessionStore, $product);

        $this->actingAs($host, 'api')
            ->postJson("/api/v1/squad/{$session->id}/checkout", ['store_id' => $other->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ITEMS_UNAVAILABLE');
    }

    public function test_squad_checkout_with_available_item_passes_the_guard(): void
    {
        $store = Store::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['base_price' => 50.00]);
        $product->stores()->attach($store->id, ['is_available' => true]);
        [$host, $session] = $this->squadWithItem($store, $product);

        $res = $this->actingAs($host, 'api')
            ->postJson("/api/v1/squad/{$session->id}/checkout", ['store_id' => $store->id]);
        // Past the guard: split-pay was set up (later steps are not under test).
        $this->assertNotSame('ITEMS_UNAVAILABLE', $res->json('error.code'), $res->getContent());
        $this->assertGreaterThan(0, SquadPayment::count());
    }

    private function appCheckoutWith(Product $product, Store $store)
    {
        $customer = Customer::factory()->create();
        $cash = PaymentMethod::create(['name' => 'Cash', 'code' => 'cash', 'is_active' => true]);
        $this->actingAs($customer, 'api')->withHeaders(['X-Kippis-Platform' => 'ios'])
            ->postJson('/api/v1/cart/items', ['item_type' => 'product', 'product_id' => $product->id, 'quantity' => 1, 'store_id' => $store->id])
            ->assertStatus(201);

        return $this->actingAs($customer, 'api')->withHeaders(['X-Kippis-Platform' => 'ios'])
            ->postJson('/api/v1/orders/checkout', ['payment_method_id' => $cash->id, 'store_id' => $store->id]);
    }

    public function test_app_checkout_rejects_item_switched_off_at_branch(): void
    {
        $store = Store::factory()->create(['is_active' => true, 'receive_online_orders' => true]);
        $product = Product::factory()->create(['base_price' => 50.00]);
        $product->stores()->attach($store->id, ['is_available' => true]);
        // Add while available, then the branch switches it off before pay.
        $customerCheckout = function () use ($product, $store) {
            $product->stores()->updateExistingPivot($store->id, ['is_available' => false]);
        };
        $customer = Customer::factory()->create();
        $cash = PaymentMethod::create(['name' => 'Cash', 'code' => 'cash', 'is_active' => true]);
        $this->actingAs($customer, 'api')->withHeaders(['X-Kippis-Platform' => 'ios'])
            ->postJson('/api/v1/cart/items', ['item_type' => 'product', 'product_id' => $product->id, 'quantity' => 1, 'store_id' => $store->id])
            ->assertStatus(201);
        $customerCheckout();

        $this->actingAs($customer, 'api')->withHeaders(['X-Kippis-Platform' => 'ios'])
            ->postJson('/api/v1/orders/checkout', ['payment_method_id' => $cash->id, 'store_id' => $store->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ITEMS_UNAVAILABLE')
            ->assertJsonPath('error.product_ids', [$product->id]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_app_checkout_with_available_item_passes_the_guard(): void
    {
        $store = Store::factory()->create(['is_active' => true, 'receive_online_orders' => true]);
        $product = Product::factory()->create(['base_price' => 50.00]);
        $product->stores()->attach($store->id, ['is_available' => true]);

        $res = $this->appCheckoutWith($product, $store);
        $this->assertNotSame('ITEMS_UNAVAILABLE', $res->json('error.code'), $res->getContent());
    }
}
