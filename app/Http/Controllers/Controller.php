<?php

namespace App\Http\Controllers;

use App\Core\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

abstract class Controller
{
    /**
     * Block items a branch has switched off (Foodics per-branch inactive /
     * out of stock, mirrored into product_store.is_available by the
     * catalog sync), items not on its menu, and inactive/draft products.
     *
     * @param  Collection<int, array{product_id: int|null, name: string|null}>  $lines
     * @param  bool  $requireActive  false leaves inactive/draft products to the caller's own check.
     * @return JsonResponse|null  422 ITEMS_UNAVAILABLE, or null when all are available.
     */
    protected function guardItemsAvailableAtStore(Collection $lines, int $storeId, bool $requireActive = true): ?JsonResponse
    {
        $productIds = $lines->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($storeId <= 0 || $productIds->isEmpty()) {
            return null;
        }

        $availableIds = Product::query()
            ->whereIn('id', $productIds)
            ->when($requireActive, fn ($q) => $q->active())
            ->availableAtStore($storeId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
        $unavailable = $productIds->diff($availableIds)->values();

        if ($unavailable->isEmpty()) {
            return null;
        }

        $names = $lines
            ->whereIn('product_id', $unavailable->all())
            ->pluck('name')
            ->filter()
            ->unique()
            ->implode(', ');

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'ITEMS_UNAVAILABLE',
                'message' => app()->getLocale() === 'ar'
                    ? 'بعض المنتجات غير متاحة في هذا الفرع حالياً: ' . $names
                    : 'Some items are not available at this branch right now: ' . $names,
                'product_ids' => $unavailable->all(),
            ],
        ], 422);
    }
}
