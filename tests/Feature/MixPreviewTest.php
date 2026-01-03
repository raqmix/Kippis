<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Core\Models\Product;
use App\Core\Models\Modifier;

class MixPreviewTest extends TestCase
{
    public function test_mix_preview_returns_total_and_breakdown(): void
    {
        $product = Product::factory()->create(['base_price' => 10.00]);
        $modifier = Modifier::factory()->create(['price' => 2.50, 'max_level' => 5]);

        $payload = [
            'configuration' => [
                'base_id' => $product->id,
                'modifiers' => [ ['id' => $modifier->id, 'level' => 2] ],
            ],
        ];

        $response = $this->postJson('/api/v1/mix/preview', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.total', 15.00); // 10.00 + (2.50 * 2)
        $this->assertArrayHasKey('breakdown', $response->json('data'));
        
        $breakdown = $response->json('data.breakdown');
        $this->assertCount(2, $breakdown); // Base + Modifier
        $this->assertEquals('base', $breakdown[0]['type']);
        $this->assertEquals('modifier', $breakdown[1]['type']);
    }

    public function test_mix_preview_with_extras(): void
    {
        $baseProduct = Product::factory()->create(['base_price' => 12.00]);
        $extraProduct = Product::factory()->create(['base_price' => 5.00]);
        $modifier = Modifier::factory()->create(['price' => 1.00, 'max_level' => 3]);

        $payload = [
            'configuration' => [
                'base_id' => $baseProduct->id,
                'modifiers' => [ ['id' => $modifier->id, 'level' => 1] ],
                'extras' => [$extraProduct->id],
            ],
        ];

        $response = $this->postJson('/api/v1/mix/preview', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.total', 18.00); // 12.00 + 1.00 + 5.00
    }

    public function test_mix_preview_validates_modifier_level(): void
    {
        $product = Product::factory()->create(['base_price' => 10.00]);
        $modifier = Modifier::factory()->create(['price' => 2.50, 'max_level' => 3]);

        $payload = [
            'configuration' => [
                'base_id' => $product->id,
                'modifiers' => [ ['id' => $modifier->id, 'level' => 5] ], // Exceeds max_level
            ],
        ];

        $response = $this->postJson('/api/v1/mix/preview', $payload);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'INVALID_CONFIGURATION');
    }
}
