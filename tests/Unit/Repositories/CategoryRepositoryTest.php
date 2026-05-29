<?php

namespace Tests\Unit\Repositories;

use App\Core\Models\Category;
use App\Core\Models\Product;
use App\Core\Repositories\CategoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CategoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CategoryRepository();
    }

    public function test_can_get_paginated_categories(): void
    {
        Category::factory()->count(20)->create()
            ->each(fn (Category $c) => Product::factory()->create(['category_id' => $c->id]));

        $result = $this->repository->getPaginated([], 10);

        $this->assertCount(10, $result->items());
        $this->assertEquals(20, $result->total());
    }

    public function test_can_filter_by_source(): void
    {
        $local = Category::factory()->create(['external_source' => 'local']);
        $foodics = Category::factory()->create(['external_source' => 'foodics']);
        Product::factory()->create(['category_id' => $local->id]);
        Product::factory()->create(['category_id' => $foodics->id]);

        $localResult = $this->repository->getPaginated(['source' => 'local'], 10);
        $foodicsResult = $this->repository->getPaginated(['source' => 'foodics'], 10);

        $this->assertEquals(1, $localResult->total());
        $this->assertEquals(1, $foodicsResult->total());
    }

    public function test_can_find_by_id(): void
    {
        $category = Category::factory()->create();

        $found = $this->repository->findById($category->id);

        $this->assertNotNull($found);
        $this->assertEquals($category->id, $found->id);
    }

    public function test_can_find_by_foodics_id(): void
    {
        $category = Category::factory()->create(['foodics_id' => 'FOODICS_123']);

        $found = $this->repository->findByFoodicsId('FOODICS_123');

        $this->assertNotNull($found);
        $this->assertEquals($category->id, $found->id);
    }
}

