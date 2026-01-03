<?php

namespace App\Core\Repositories;

use App\Core\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PageRepository
{
    /**
     * Get paginated pages with filters.
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Page::with(['translations' => function ($q) {
            $q->where('locale', app()->getLocale());
        }]);

        // Filter by is_active (default to active only if not specified)
        if (isset($filters['is_active'])) {
            if ($filters['is_active'] === '1' || $filters['is_active'] === true) {
                $query->where('is_active', true);
            } elseif ($filters['is_active'] === '0' || $filters['is_active'] === false) {
                $query->where('is_active', false);
            }
        } else {
            $query->where('is_active', true);
        }

        // Filter by type
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Search
        if (isset($filters['q']) && $filters['q']) {
            $q = $filters['q'];
            $query->whereHas('translations', function ($qry) use ($q) {
                $qry->where('title', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        // Validate sort_by
        $allowedSorts = ['created_at', 'slug', 'type', 'updated_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        
        // Validate sort_order must be 'asc' or 'desc'
        if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }
        
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get all active pages.
     */
    public function getAllActive(array $filters = []): Collection
    {
        $query = Page::with(['translations' => function ($q) {
            $q->where('locale', app()->getLocale());
        }])->where('is_active', true);

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->orderBy('slug')->get();
    }

    /**
     * Find page by ID.
     */
    public function findById(int $id): ?Page
    {
        return Page::with(['translations' => function ($q) {
            $q->where('locale', app()->getLocale());
        }])->find($id);
    }

    /**
     * Find page by slug.
     */
    public function findBySlug(string $slug): ?Page
    {
        return Page::with(['translations' => function ($q) {
            $q->where('locale', app()->getLocale());
        }])->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get pages by type.
     */
    public function getByType(string $type): Collection
    {
        return Page::with(['translations' => function ($q) {
            $q->where('locale', app()->getLocale());
        }])->where('type', $type)
            ->where('is_active', true)
            ->orderBy('slug')
            ->get();
    }
}

