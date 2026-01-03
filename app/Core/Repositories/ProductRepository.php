<?php

namespace App\Core\Repositories;

use App\Core\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    /**
     * Get paginated products with filters.
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::with('category');

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

        // Exclude mix bases from regular product listings unless explicitly requested
        if (!isset($filters['include_bases']) || $filters['include_bases'] !== true) {
            $query->regular(); // Only show regular products by default
        }

        // Filter by source
        if (isset($filters['source'])) {
            if ($filters['source'] === 'local') {
                $query->local();
            } elseif ($filters['source'] === 'foodics') {
                $query->foodics();
            }
        }

        // Filter by category
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Filter by store (if products have store relationship)
        if (isset($filters['store_id'])) {
            // Products don't have direct store relationship, but categories might
            // This would need to be implemented based on your business logic
        }

        // Price range filters
        if (isset($filters['price_min'])) {
            $query->where('base_price', '>=', $filters['price_min']);
        }
        if (isset($filters['price_max'])) {
            $query->where('base_price', '<=', $filters['price_max']);
        }

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
        
        // Validate sort_by to prevent SQL injection
        $allowedSorts = ['created_at', 'base_price', 'name', 'updated_at'];
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
     * Get all active products.
     */
    public function getAllActive(array $filters = []): Collection
    {
        $query = Product::with('category')->active();

        // Exclude mix bases from regular product listings unless explicitly requested
        if (!isset($filters['include_bases']) || $filters['include_bases'] !== true) {
            $query->regular();
        }

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

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
     * Find product by ID.
     */
    public function findById(int $id): ?Product
    {
        return Product::with(['category', 'addonModifiers'])->active()->find($id);
    }

    /**
     * Find product by Foodics ID.
     */
    public function findByFoodicsId(string $foodicsId): ?Product
    {
        return Product::where('foodics_id', $foodicsId)->first();
    }
}

