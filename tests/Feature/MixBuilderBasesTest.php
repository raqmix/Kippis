<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Core\Models\Product;
use App\Core\Models\Modifier;
use App\Core\Models\MixBuilderBase;

class MixBuilderBasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_mix_builder_options_returns_bases_list(): void
    {
        // Create a mix base product
        $base = Product::factory()->create([
            'product_kind' => 'mix_base',
            'base_price' => 15.00,
            'is_active' => true,
        ]);

        // Create a regular product (should not appear in bases)
        $regularProduct = Product::factory()->create([
            'product_kind' => 'regular',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/mix/options');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'bases' => [
                    '*' => ['id', 'name', 'base_price']
                ],
                'modifiers'
            ]
        ]);

        $bases = $response->json('data.bases');
        $this->assertNotEmpty($bases);
        $this->assertTrue(collect($bases)->contains('id', $base->id));
        $this->assertFalse(collect($bases)->contains('id', $regularProduct->id));
    }

    public function test_mix_builder_options_filters_bases_by_builder_id(): void
    {
        $builderId = 1;
        
        $base1 = Product::factory()->create([
            'product_kind' => 'mix_base',
            'is_active' => true,
        ]);

        $base2 = Product::factory()->create([
            'product_kind' => 'mix_base',
            'is_active' => true,
        ]);

        // Assign base1 to builder 1
        MixBuilderBase::create([
            'mix_builder_id' => $builderId,
            'product_id' => $base1->id,
        ]);

        // base2 is not assigned to builder 1 (global or unassigned)

        $response = $this->getJson("/api/v1/mix/options?builder_id={$builderId}");

        $response->assertStatus(200);
        $bases = $response->json('data.bases');
        
        // Should include base1 (assigned) and base2 if it's global (null mix_builder_id)
        $baseIds = collect($bases)->pluck('id')->toArray();
        $this->assertContains($base1->id, $baseIds);
    }

    public function test_mix_preview_validates_base_belongs_to_builder(): void
    {
        $builderId = 1;
        
        $base = Product::factory()->create([
            'product_kind' => 'mix_base',
            'base_price' => 15.00,
            'is_active' => true,
        ]);

        // Don't assign base to builder

        $payload = [
            'configuration' => [
                'base_id' => $base->id,
                'builder_id' => $builderId,
                'modifiers' => [],
            ],
        ];

        $response = $this->postJson('/api/v1/mix/preview', $payload);

        // A base assigned to no builder (and not global) is rejected when a
        // builder_id is supplied — the calculator enforces builder membership.
        $response->assertStatus(400);
        $response->assertJsonPath('error.code', 'INVALID_CONFIGURATION');
    }

    public function test_mix_preview_rejects_invalid_base_for_builder(): void
    {
        $builderId = 1;
        $otherBuilderId = 2;
        
        $base = Product::factory()->create([
            'product_kind' => 'mix_base',
            'base_price' => 15.00,
            'is_active' => true,
        ]);

        // Assign base to otherBuilderId only
        MixBuilderBase::create([
            'mix_builder_id' => $otherBuilderId,
            'product_id' => $base->id,
        ]);

        $payload = [
            'configuration' => [
                'base_id' => $base->id,
                'builder_id' => $builderId, // Different builder
                'modifiers' => [],
            ],
        ];

        $response = $this->postJson('/api/v1/mix/preview', $payload);

        // Should fail because base is assigned to otherBuilderId, not builderId
        $response->assertStatus(400);
        $response->assertJsonPath('error.code', 'INVALID_CONFIGURATION');
    }

    public function test_regular_products_excluded_from_bases_list(): void
    {
        $regularProduct = Product::factory()->create([
            'product_kind' => 'regular',
            'is_active' => true,
        ]);

        $base = Product::factory()->create([
            'product_kind' => 'mix_base',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/mix/options');

        $response->assertStatus(200);
        $bases = $response->json('data.bases');
        $baseIds = collect($bases)->pluck('id')->toArray();

        $this->assertNotContains($regularProduct->id, $baseIds);
        $this->assertContains($base->id, $baseIds);
    }

    public function test_backward_compatibility_no_builder_id_any_mix_base_works(): void
    {
        $base = Product::factory()->create([
            'product_kind' => 'mix_base',
            'base_price' => 15.00,
            'is_active' => true,
        ]);

        $modifier = Modifier::factory()->create(['price' => 2.00, 'max_level' => 5]);

        $payload = [
            'configuration' => [
                'base_id' => $base->id,
                'modifiers' => [['id' => $modifier->id, 'level' => 1]],
            ],
        ];

        $response = $this->postJson('/api/v1/mix/preview', $payload);

        // Should work without builder_id (backward compatibility)
        $response->assertStatus(200);
        $this->assertEquals(17.00, (float) $response->json('data.total')); // 15.00 + 2.00
    }

    public function test_mix_base_product_kind_validation(): void
    {
        $regularProduct = Product::factory()->create([
            'product_kind' => 'regular',
            'base_price' => 15.00,
            'is_active' => true,
        ]);

        $payload = [
            'configuration' => [
                'base_id' => $regularProduct->id,
                'modifiers' => [],
            ],
        ];

        $response = $this->postJson('/api/v1/mix/preview', $payload);

        // Should fail because regular product cannot be used as base
        $response->assertStatus(400);
        $response->assertJsonPath('error.code', 'INVALID_CONFIGURATION');
    }
}

