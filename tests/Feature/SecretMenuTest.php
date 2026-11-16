<?php

namespace Tests\Feature;

use App\Core\Models\Category;
use App\Core\Models\Creator;
use App\Core\Models\CreatorDrop;
use App\Core\Models\Customer;
use App\Core\Models\Order;
use App\Core\Models\Product;
use App\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Brief section F: app-only "secret menu" products. */
class SecretMenuTest extends TestCase
{
    use RefreshDatabase;

    private const MOBILE = ['X-Kippis-Platform' => 'ios'];
    private const WEB = ['X-Kippis-Platform' => 'web'];

    private string $apiKey = 'test-kiosk-key';

    private Store $store;
    private Category $category;
    private Product $normal;
    private Product $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::factory()->create([
            'is_active' => true,
            'receive_online_orders' => true,
            'kiosk_api_key' => hash('sha256', $this->apiKey),
        ]);
        $this->category = Category::factory()->create(['is_active' => true]);
        $this->normal = Product::factory()->create(['category_id' => $this->category->id, 'name_json' => ['en' => 'Normal Latte', 'ar' => 'عادي']]);
        $this->secret = Product::factory()->create(['category_id' => $this->category->id, 'app_only' => true, 'name_json' => ['en' => 'Secret Latte', 'ar' => 'سري']]);
    }

    private function kioskHeaders(): array
    {
        return ['X-Store-ID' => (string) $this->store->id, 'X-Kiosk-API-Key' => $this->apiKey];
    }

    private function ids(array $products): array
    {
        return collect($products)->pluck('id')->sort()->values()->all();
    }

    public function test_product_list_shows_secret_items_to_mobile_only(): void
    {
        foreach (['/api/v1/catalog/products', '/api/v1/products'] as $url) {
            $mobile = $this->withHeaders(self::MOBILE)->getJson($url)->assertOk();
            $this->assertEqualsCanonicalizing([$this->normal->id, $this->secret->id], $this->ids($mobile->json('data')));
            $secret = collect($mobile->json('data'))->firstWhere('id', $this->secret->id);
            $this->assertTrue($secret['app_only']);
            $this->assertFalse(collect($mobile->json('data'))->firstWhere('id', $this->normal->id)['app_only']);

            $web = $this->withHeaders(self::WEB)->getJson($url)->assertOk();
            $this->assertSame([$this->normal->id], $this->ids($web->json('data')));

            $anon = $this->getJson($url)->assertOk();
            $this->assertSame([$this->normal->id], $this->ids($anon->json('data')));
        }
    }

    public function test_dart_user_agent_counts_as_mobile(): void
    {
        $res = $this->withHeaders(['User-Agent' => 'Dart/3.5 (dart:io)'])->getJson('/api/v1/catalog/products')->assertOk();
        $this->assertCount(2, $res->json('data'));
    }

    public function test_search_hides_secret_items_off_app(): void
    {
        $this->withHeaders(self::MOBILE)->getJson('/api/v1/catalog/products?q=Secret')
            ->assertOk()->assertJsonCount(1, 'data');
        $this->withHeaders(self::WEB)->getJson('/api/v1/catalog/products?q=Secret')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_product_detail(): void
    {
        $this->withHeaders(self::MOBILE)->getJson("/api/v1/catalog/products/{$this->secret->id}")
            ->assertOk()->assertJsonPath('data.app_only', true);
        $this->withHeaders(self::WEB)->getJson("/api/v1/catalog/products/{$this->secret->id}")->assertNotFound();
        $this->getJson("/api/v1/products/{$this->secret->id}")->assertNotFound();
    }

    public function test_home_featured_products(): void
    {
        foreach (['/api/v1/catalog/home', '/api/v1/home'] as $url) {
            $mobile = $this->withHeaders(self::MOBILE)->getJson($url)->assertOk();
            $this->assertContains($this->secret->id, $this->ids($mobile->json('data.featured_products')));
            $web = $this->withHeaders(self::WEB)->getJson($url)->assertOk();
            $this->assertNotContains($this->secret->id, $this->ids($web->json('data.featured_products')));
        }
    }

    public function test_category_with_only_secret_products_is_hidden_off_app(): void
    {
        $secretOnly = Category::factory()->create(['is_active' => true]);
        Product::factory()->create(['category_id' => $secretOnly->id, 'app_only' => true]);

        $mobile = $this->withHeaders(self::MOBILE)->getJson('/api/v1/catalog/categories')->assertOk();
        $this->assertContains($secretOnly->id, $this->ids($mobile->json('data')));
        $web = $this->withHeaders(self::WEB)->getJson('/api/v1/catalog/categories')->assertOk();
        $this->assertNotContains($secretOnly->id, $this->ids($web->json('data')));
        $this->assertContains($this->category->id, $this->ids($web->json('data')));
    }

    public function test_kiosk_never_shows_secret_items_even_with_mobile_header(): void
    {
        $headers = $this->kioskHeaders() + self::MOBILE;
        $secretOnly = Category::factory()->create(['is_active' => true]);
        Product::factory()->create(['category_id' => $secretOnly->id, 'app_only' => true]);

        $products = $this->withHeaders($headers)->getJson('/api/v1/kiosk/products')->assertOk();
        $this->assertSame([$this->normal->id], $this->ids($products->json('data')));

        $this->withHeaders($headers)->getJson("/api/v1/kiosk/products/{$this->secret->id}")->assertNotFound();

        $home = $this->withHeaders($headers)->getJson('/api/v1/kiosk/home')->assertOk();
        $this->assertNotContains($this->secret->id, $this->ids($home->json('data.featured_products')));

        $cats = $this->withHeaders($headers)->getJson('/api/v1/kiosk/categories')->assertOk();
        $this->assertNotContains($secretOnly->id, $this->ids($cats->json('data')));
    }

    public function test_kiosk_top_picks_skip_secret_items(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->completed()->create([
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'created_at' => now()->subDay(),
            'items_snapshot' => [
                ['product_id' => $this->secret->id, 'quantity' => 5],
                ['product_id' => $this->normal->id, 'quantity' => 1],
            ],
        ]);

        $res = $this->withHeaders($this->kioskHeaders())->getJson('/api/v1/kiosk/top-picks')->assertOk();
        $this->assertSame([$this->normal->id], $this->ids($res->json('data.top_picks')));
    }

    public function test_creator_drops_hide_secret_products_off_app(): void
    {
        $creator = Creator::create(['name_en' => 'C', 'name_ar' => 'ك', 'is_active' => true]);
        $make = fn (Product $p) => CreatorDrop::create([
            'creator_id' => $creator->id, 'product_id' => $p->id,
            'title_en' => 'T ' . $p->id, 'title_ar' => 'ع',
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'status' => 'live',
        ]);
        $normalDrop = $make($this->normal);
        $secretDrop = $make($this->secret);

        $mobile = $this->withHeaders(self::MOBILE)->getJson('/api/v1/drops')->assertOk();
        $this->assertEqualsCanonicalizing([$normalDrop->id, $secretDrop->id], $this->ids($mobile->json('data.drops')));
        $web = $this->withHeaders(self::WEB)->getJson('/api/v1/drops')->assertOk();
        $this->assertSame([$normalDrop->id], $this->ids($web->json('data.drops')));

        $this->withHeaders(self::MOBILE)->getJson("/api/v1/drops/{$secretDrop->id}")->assertOk();
        $this->withHeaders(self::WEB)->getJson("/api/v1/drops/{$secretDrop->id}")->assertNotFound();

        $kiosk = $this->withHeaders($this->kioskHeaders() + self::MOBILE)->getJson('/api/v1/kiosk/drops')->assertOk();
        $this->assertSame([$normalDrop->id], $this->ids($kiosk->json('data.drops')));
    }

    public function test_cart_add_rejects_secret_product_off_app(): void
    {
        $customer = Customer::factory()->create();
        $add = fn (array $headers, Product $p) => $this->actingAs($customer, 'api')->withHeaders($headers)
            ->postJson('/api/v1/cart/items', ['item_type' => 'product', 'product_id' => $p->id, 'quantity' => 1, 'store_id' => $this->store->id]);

        $add(self::WEB, $this->secret)->assertStatus(422)->assertJsonPath('error.code', 'APP_ONLY_PRODUCT');
        $add([], $this->secret)->assertStatus(422);
        $add(self::WEB, $this->normal)->assertStatus(201);
        $add(self::MOBILE, $this->secret)->assertStatus(201);
    }

    public function test_kiosk_cart_rejects_secret_product(): void
    {
        $res = $this->withHeaders($this->kioskHeaders() + self::MOBILE)
            ->postJson('/api/v1/kiosk/cart/items', ['item_type' => 'product', 'product_id' => $this->secret->id, 'quantity' => 1]);
        $this->assertSame(422, $res->status());
    }
}
