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

        // Filter by is_active (default to active only if not specified)
        if (isset($filters['is_active'])) {
            if ($filters['is_active'] === '1' || $filters['is_active'] === true) {
                $query->active();
            } elseif ($filters['is_active'] === '0' || $filters['is_active'] === false) {
                $query->where('is_active', false);
            }
        } else {
            $query->active();
        }

        // Filter by source
        if (isset($filters['source'])) {
            if ($filters['source'] === 'local') {
                $query->local();
            } elseif ($filters['source'] === 'foodics') {
                $query->foodics();
            }
        }

        // Only get categories that have active products
        $query->whereHas('products', function ($q) {
            $q->where('is_active', true);
        });

        // Search
        if (isset($filters['q']) && $filters['q']) {
            $q = $filters['q'];
            $query->where(function ($qry) use ($q) {
                $qry->whereRaw("JSON_EXTRACT(name_json, '$.en') LIKE ?", ["%{$q}%"])
                    ->orWhereRaw("JSON_EXTRACT(name_json, '$.ar') LIKE ?", ["%{$q}%"])
                    ->orWhereRaw("JSON_EXTRACT(description_json, '$.en') LIKE ?", ["%{$q}%"])
                    ->orWhereRaw("JSON_EXTRACT(description_json, '$.ar') LIKE ?", ["%{$q}%"]);
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        // Validate sort_by
        $allowedSorts = ['created_at', 'name', 'updated_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        
        // Validate sort_order must be 'asc' or 'desc'
        if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }
        
        if ($sortBy === 'name') {
            $query->orderByRaw("JSON_EXTRACT(name_json, '$.en') {$sortOrder}");
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

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

        // Only get categories that have active products
        $query->whereHas('products', function ($q) {
            $q->where('is_active', true);
        });

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

