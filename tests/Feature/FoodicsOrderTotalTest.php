<?php

namespace Tests\Feature;

use App\Core\Models\Order;
use App\Jobs\PushOrderToFoodics;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Guards the discount arithmetic in the Foodics order payload.
 *
 * The bug: product lines are sent at their full, undiscounted unit prices
 * and the discount is sent separately as `discount_amount`, but `total` was
 * being sent post-discount. Foodics was therefore handed a payload that
 * contradicted itself — lines summing to 25.00, a 3.75 discount, and a
 * declared total of 21.25 — and settled the difference as change, printing
 * "25.00 paid, 3.75 change" on a check the customer paid 21.25 for.
 *
 * Numbers below are the reported case: Small Water 25.00, discount 3.75.
 */
class FoodicsOrderTotalTest extends TestCase
{
    /**
     * The payload builder resolves Kippis product ids to Foodics ids, so it
     * needs a products table. Building just that table keeps these tests off
     * the full migration set, which contains MySQL-only DDL.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('products');
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('foodics_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        DB::table('products')->insert([
            ['id' => 1, 'foodics_id' => 'fd-prod-1', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'foodics_id' => 'fd-prod-2', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Eager-loaded by the payload builder. Left empty: these cases are
        // about discount arithmetic on plain lines, not modifier pricing.
        Schema::dropIfExists('product_foodics_modifiers');
        Schema::create('product_foodics_modifiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('foodics_modifier_id');
            $table->integer('minimum_options')->nullable();
            $table->integer('maximum_options')->nullable();
            $table->integer('free_options')->nullable();
            $table->text('default_option_ids')->nullable();
            $table->text('excluded_option_ids')->nullable();
            $table->boolean('unique_options')->default(false);
            $table->boolean('is_splittable_in_half')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::dropIfExists('foodics_modifiers');
        Schema::create('foodics_modifiers', function (Blueprint $table) {
            $table->id();
            $table->string('foodics_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('foodics_modifier_options');
        Schema::create('foodics_modifier_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('foodics_modifier_id');
            $table->string('foodics_id')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Build the payload without touching the network.
     * mapToFoodicsPayload only reads attributes off the Order, so an
     * unsaved model is enough and keeps this test independent of the
     * MySQL-only migrations.
     */
    private function payloadFor(float $subtotal, float $discount, float $total, array $items): array
    {
        $order = new Order();
        $order->forceFill([
            'id' => 104,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'items_snapshot' => $items,
            'pickup_code' => '8873',
        ]);

        $job = new PushOrderToFoodics($order->id);

        $method = (new \ReflectionClass($job))->getMethod('mapToFoodicsPayload');
        $method->setAccessible(true);

        return $method->invoke($job, $order, 'branch-test');
    }

    public function test_discounted_order_payload_reconciles_to_the_amount_charged(): void
    {
        $payload = $this->payloadFor(
            subtotal: 25.00,
            discount: 3.75,
            total: 21.25,
            items: [[
                'product_id' => 1,
                'quantity' => 1,
                'price' => 25.00,
                'modifiers' => [],
            ]],
        );

        // Pre-discount, matching the undiscounted product lines.
        $this->assertSame(25.00, $payload['total'], 'total must be sent pre-discount');
        $this->assertSame(3.75, $payload['discount_amount']);
        $this->assertSame(1, $payload['discount_type'], '1 = Value in the Foodics v5 enum');

        // The invariant that failed in production: what Foodics computes
        // from the payload must equal what the customer actually paid.
        $this->assertEqualsWithDelta(
            21.25,
            $payload['total'] - $payload['discount_amount'],
            0.01,
            'Foodics must arrive at the charged amount, leaving no change to settle'
        );
    }

    public function test_undiscounted_order_omits_the_discount_fields(): void
    {
        $payload = $this->payloadFor(
            subtotal: 25.00,
            discount: 0.0,
            total: 25.00,
            items: [[
                'product_id' => 1,
                'quantity' => 1,
                'price' => 25.00,
                'modifiers' => [],
            ]],
        );

        $this->assertSame(25.00, $payload['total']);
        $this->assertArrayNotHasKey('discount_amount', $payload, 'Foodics reads absence as no discount');
        $this->assertArrayNotHasKey('discount_type', $payload);
    }

    public function test_multi_item_discounted_order_reconciles(): void
    {
        $payload = $this->payloadFor(
            subtotal: 70.00,
            discount: 10.50,
            total: 59.50,
            items: [
                ['product_id' => 1, 'quantity' => 2, 'price' => 25.00, 'modifiers' => []],
                ['product_id' => 2, 'quantity' => 1, 'price' => 20.00, 'modifiers' => []],
            ],
        );

        $this->assertSame(70.00, $payload['total']);
        $this->assertEqualsWithDelta(59.50, $payload['total'] - $payload['discount_amount'], 0.01);
    }
}
