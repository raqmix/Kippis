<?php

namespace App\Core\Repositories;

use App\Core\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository
{
    /**
     * Get paginated categories with filters.
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Category::query();

        // Filter by source
        if (isset($filters['source'])) {
            if ($filters['source'] === 'local') {
                $query->local();
            } elseif ($filters['source'] === 'foodics') {
                $query->foodics();
            }
        }

        // Only active
        $query->active();

        return $query->paginate($perPage);
    }

    /**
     * Get all active categories.
     */
    public function getAllActive(array $filters = []): Collection
    {
        $query = Category::active();

        if (isset($filters['source'])) {
            if ($filters['source'] === 'local') {
                $query->local();
            } elseif ($filters['source'] === 'foodics') {
                $query->foodics();
            }
        }

        return $query->get();
    }

    /**
     * Find category by ID.
     */
    public function findById(int $id): ?Category
    {
        return Category::find($id);
    }

    /**
     * Find category by Foodics ID.
     */
    public function findByFoodicsId(string $foodicsId): ?Category
    {
        return Category::where('foodics_id', $foodicsId)->first();
    }
}

