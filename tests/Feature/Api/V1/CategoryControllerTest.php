<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\Category;
use App\Core\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_categories(): void
    {
        Category::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'name_ar', 'name_en', 'image', 'external_source'],
                ],
                'meta',
            ]);
    }

    public function test_can_filter_by_source(): void
    {
        $local = Category::factory()->create(['external_source' => 'local']);
        $foodics = Category::factory()->create(['external_source' => 'foodics']);
        Product::factory()->create(['category_id' => $local->id]);
        Product::factory()->create(['category_id' => $foodics->id]);

        $response = $this->getJson('/api/v1/categories?source=local');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_supports_pagination(): void
    {
        Category::factory()->count(25)->create()
            ->each(fn (Category $c) => Product::factory()->create(['category_id' => $c->id]));

        $response = $this->getJson('/api/v1/categories?per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25);
    }
}

