<?php

namespace App\Core\Repositories;

use App\Core\Models\PaymentMethod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PaymentMethodRepository
{
    /**
     * Get paginated payment methods with filters.
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PaymentMethod::with('channel');

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

        // Filter by channel
        if (isset($filters['channel_id'])) {
            $query->where('channel_id', $filters['channel_id']);
        }

        // Search
        if (isset($filters['q']) && $filters['q']) {
            $q = $filters['q'];
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        // Validate sort_by
        $allowedSorts = ['created_at', 'name', 'code', 'updated_at'];
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
     * Get all active payment methods.
     */
    public function getAllActive(array $filters = []): Collection
    {
        $query = PaymentMethod::with('channel')->where('is_active', true);

        if (isset($filters['channel_id'])) {
            $query->where('channel_id', $filters['channel_id']);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Find payment method by ID.
     */
    public function findById(int $id): ?PaymentMethod
    {
        return PaymentMethod::with('channel')->find($id);
    }

    /**
     * Find payment method by code.
     */
    public function findByCode(string $code): ?PaymentMethod
    {
        return PaymentMethod::where('code', $code)->where('is_active', true)->first();
    }
}

