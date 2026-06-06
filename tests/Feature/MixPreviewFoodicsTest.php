<?php

namespace Tests\Feature;

use App\Core\Models\FoodicsModifier;
use App\Core\Models\FoodicsModifierOption;
use App\Core\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MixPreviewFoodicsTest extends TestCase
{
    use RefreshDatabase;

    private function seedFoodicsMix(): array
    {
        $product = Product::factory()->create([
            'base_price' => 150.00,
            'is_active' => true,
        ]);
        Config::set('mix.foodics_product_id', $product->id);

        $modifier = FoodicsModifier::create([
            'foodics_id' => 'fm-test',
            'name_json' => ['en' => 'Base Modifiers', 'ar' => 'Base Modifiers'],
            'is_active' => true,
        ]);

        $option1 = FoodicsModifierOption::create([
            'foodics_id' => 'opt-1',
            'foodics_modifier_id' => $modifier->id,
            'name_json' => ['en' => 'Hibiscus', 'ar' => 'Hibiscus'],
            'price' => 50.00,
            'is_active' => true,
        ]);
        $option2 = FoodicsModifierOption::create([
            'foodics_id' => 'opt-2',
            'foodics_modifier_id' => $modifier->id,
            'name_json' => ['en' => 'Mango', 'ar' => 'Mango'],
            'price' => 30.00,
            'is_active' => true,
        ]);

        return [$product, $option1, $option2];
    }

    public function test_preview_with_foodics_option_ids_returns_total_and_breakdown(): void
    {
        [$product, $option1, $option2] = $this->seedFoodicsMix();

        $response = $this->postJson('/api/v1/mix/preview', [
            'configuration' => [
                'foodics_option_ids' => [$option1->id, $option2->id],
            ],
        ]);

        $response->assertStatus(200);
        // 150 base + 50 + 30
        $this->assertEquals(230.00, (float) $response->json('data.total'));

        $breakdown = $response->json('data.breakdown');
        $this->assertCount(3, $breakdown);
        $this->assertEquals('product', $breakdown[0]['type']);
        $this->assertEquals('foodics_option', $breakdown[1]['type']);
        $this->assertEquals('foodics_option', $breakdown[2]['type']);
    }

    public function test_preview_ignores_inactive_foodics_options(): void
    {
        [$product, $option1, $option2] = $this->seedFoodicsMix();
        $option2->update(['is_active' => false]);

        $response = $this->postJson('/api/v1/mix/preview', [
            'configuration' => [
                'foodics_option_ids' => [$option1->id, $option2->id],
            ],
        ]);

        $response->assertStatus(200);
        // 150 + 50 only — the inactive option is dropped
        $this->assertEquals(200.00, (float) $response->json('data.total'));
    }

    public function test_preview_rejects_unknown_foodics_option_id(): void
    {
        $this->seedFoodicsMix();

        $response = $this->postJson('/api/v1/mix/preview', [
            'configuration' => [
                'foodics_option_ids' => [999999],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error' => ['errors' => ['configuration.foodics_option_ids.0']]]);
    }
}
