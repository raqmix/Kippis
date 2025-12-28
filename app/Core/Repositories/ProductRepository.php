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
        $query = Product::with('category')->active();

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

        // Search
        if (isset($filters['q']) && $filters['q']) {
            $q = $filters['q'];
            $query->where(function ($qry) use ($q) {
                $qry->whereRaw("JSON_EXTRACT(name_json, '$.en') LIKE ?", ["%{$q}%"])
                    ->orWhereRaw("JSON_EXTRACT(name_json, '$.ar') LIKE ?", ["%{$q}%"]);
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get all active products.
     */
    public function getAllActive(array $filters = []): Collection
    {
        $query = Product::with('category')->active();

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
        return Product::with('category')->active()->find($id);
    }

    /**
     * Find product by Foodics ID.
     */
    public function findByFoodicsId(string $foodicsId): ?Product
    {
        return Product::where('foodics_id', $foodicsId)->first();
    }
}

