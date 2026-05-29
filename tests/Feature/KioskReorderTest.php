<?php

namespace Tests\Feature;

use App\Core\Models\Customer;
use App\Core\Models\LoyaltyWallet;
use App\Core\Models\Order;
use App\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KioskReorderTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_wallet_gets_qr_token_on_creation(): void
    {
        $wallet = LoyaltyWallet::factory()->create();
        $this->assertNotEmpty($wallet->qr_token);
    }

    public function test_scan_by_token_returns_customer_and_last_completed_order(): void
    {
        $store = $this->kioskStore();
        $customer = Customer::factory()->create();
        $wallet = LoyaltyWallet::factory()->create(['customer_id' => $customer->id]);
        Order::factory()->completed()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->withHeaders($this->headers($store))
            ->getJson('/api/v1/kiosk/reorder/scan?qr_data=' . $wallet->qr_token);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertNotNull($response->json('data.order.id'));
    }

    public function test_scan_by_enumerable_wallet_id_is_rejected(): void
    {
        $store = $this->kioskStore();
        $customer = Customer::factory()->create();
        $wallet = LoyaltyWallet::factory()->create(['customer_id' => $customer->id]);
        Order::factory()->completed()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
        ]);

        // Passing the sequential primary key must not resolve a wallet.
        $response = $this->withHeaders($this->headers($store))
            ->getJson('/api/v1/kiosk/reorder/scan?qr_data=' . $wallet->id);

        $response->assertStatus(404);
    }

    public function test_confirm_rejects_order_not_belonging_to_scanned_wallet(): void
    {
        $store = $this->kioskStore();
        $victim = Customer::factory()->create();
        $attacker = Customer::factory()->create();
        $attackerWallet = LoyaltyWallet::factory()->create(['customer_id' => $attacker->id]);

        $victimOrder = Order::factory()->completed()->create([
            'store_id' => $store->id,
            'customer_id' => $victim->id,
        ]);

        $response = $this->withHeaders($this->headers($store))
            ->postJson('/api/v1/kiosk/reorder/confirm', [
                'qr_data' => $attackerWallet->qr_token,
                'order_id' => $victimOrder->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_confirm_returns_items_for_own_completed_order(): void
    {
        $store = $this->kioskStore();
        $customer = Customer::factory()->create();
        $wallet = LoyaltyWallet::factory()->create(['customer_id' => $customer->id]);
        $order = Order::factory()->completed()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->withHeaders($this->headers($store))
            ->postJson('/api/v1/kiosk/reorder/confirm', [
                'qr_data' => $wallet->qr_token,
                'order_id' => $order->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }
}
