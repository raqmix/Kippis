<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\Store;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StoreResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Stores
 * 
 * APIs for retrieving stores information.
 * 
 * Stores represent physical locations where customers can place orders. Only active stores that receive online orders are returned.
 */
class StoreController extends Controller
{
    /**
     * Get list of stores.
     * 
     * Retrieve a list of active stores that receive online orders. You can sort stores by name (default) or by nearest distance (requires coordinates).
     * 
     * <aside class="notice">
     * Only stores that are active and receive online orders are returned. Deleted or inactive stores are excluded.
     * </aside>
     * 
     * @queryParam latitude float optional Latitude for distance calculation (required when sort=nearest). Must be between -90 and 90. Example: 24.7136
     * @queryParam longitude float optional Longitude for distance calculation (required when sort=nearest). Must be between -180 and 180. Example: 46.6753
     * @queryParam sort string optional Sort order. Options: `nearest` (requires latitude and longitude), `name` (default). Example: nearest
     * @queryParam page int optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page int optional Number of items per page. Default: 15. Maximum: 100. Example: 15
     * 
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Downtown Store",
     *       "name_localized": {
     *         "en": "Downtown Store",
     *         "ar": "متجر وسط المدينة"
     *       },
     *       "address": "123 Main Street, Riyadh",
     *       "latitude": "24.71360000",
     *       "longitude": "46.67530000",
     *       "open_time": "09:00",
     *       "close_time": "22:00",
     *       "is_open_now": true,
     *       "distance": 2.5
     *     },
     *     {
     *       "id": 2,
     *       "name": "Mall Branch",
     *       "name_localized": {
     *         "en": "Mall Branch",
     *         "ar": "فرع المول"
     *       },
     *       "address": "456 Shopping Mall, Riyadh",
     *       "latitude": "24.72000000",
     *       "longitude": "46.68000000",
     *       "open_time": "10:00",
     *       "close_time": "23:00",
     *       "is_open_now": true,
     *       "distance": 5.2
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "from": 1,
     *     "last_page": 3,
     *     "per_page": 15,
     *     "to": 15,
     *     "total": 42
     *   },
     *   "links": {
     *     "first": "http://localhost/api/v1/stores?page=1",
     *     "last": "http://localhost/api/v1/stores?page=3",
     *     "prev": null,
     *     "next": "http://localhost/api/v1/stores?page=2"
     *   }
     * }
     * 
     * @response 422 {
     *   "success": false,
     *   "error": {
     *     "code": "MISSING_COORDINATES",
     *     "message": "Latitude and longitude are required for nearest sorting."
     *   }
     * }
     * 
     * @responseField id int The store ID.
     * @responseField name string The store name.
     * @responseField name_localized object Localized store names (e.g., {"en": "Store Name", "ar": "اسم المتجر"}).
     * @responseField address string The store address.
     * @responseField latitude string The store latitude coordinate.
     * @responseField longitude string The store longitude coordinate.
     * @responseField open_time string Store opening time (HH:mm format).
     * @responseField close_time string Store closing time (HH:mm format).
     * @responseField is_open_now boolean Whether the store is currently open.
     * @responseField distance float Distance in kilometers (only included when coordinates are provided).
     * @responseField meta object Pagination metadata.
     * @responseField meta.current_page int Current page number.
     * @responseField meta.from int Starting record number for current page.
     * @responseField meta.last_page int Last page number.
     * @responseField meta.per_page int Number of items per page.
     * @responseField meta.to int Ending record number for current page.
     * @responseField meta.total int Total number of stores.
     * @responseField links object Pagination links.
     * @responseField links.first string URL to first page.
     * @responseField links.last string URL to last page.
     * @responseField links.prev string|null URL to previous page (null if on first page).
     * @responseField links.next string|null URL to next page (null if on last page).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'nullable|numeric|min:-90|max:90',
            'longitude' => 'nullable|numeric|min:-180|max:180',
            'sort' => 'nullable|in:nearest,name',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $sort = $request->input('sort', 'name'); // 'nearest' or 'name'
        $perPage = min($request->input('per_page', 15), 100); // Max 100 per page

        // Validate that lat/lng are both provided if sorting by nearest
        if ($sort === 'nearest' && ($latitude === null || $longitude === null)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MISSING_COORDINATES',
                    'message' => 'Latitude and longitude are required for nearest sorting.',
                ],
            ], 422);
        }

        $query = Store::activeForOrders();

        // Calculate distance if lat/lng provided
        if ($latitude !== null && $longitude !== null) {
            $query->selectRaw('*, (
                6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) AS distance', [$latitude, $longitude, $latitude]);
        }

        // Sort by nearest or name
        if ($sort === 'nearest' && $latitude !== null && $longitude !== null) {
            $query->orderBy('distance');
        } else {
            $query->orderBy('name');
        }

        // Paginate results
        $paginatedStores = $query->paginate($perPage);

        // Add is_open_now to each store
        $paginatedStores->getCollection()->each(function ($store) {
            $store->is_open_now = $store->isOpenNow();
        });

        return response()->json([
            'success' => true,
            'data' => StoreResource::collection($paginatedStores->items()),
            'meta' => [
                'current_page' => $paginatedStores->currentPage(),
                'from' => $paginatedStores->firstItem(),
                'last_page' => $paginatedStores->lastPage(),
                'per_page' => $paginatedStores->perPage(),
                'to' => $paginatedStores->lastItem(),
                'total' => $paginatedStores->total(),
            ],
            'links' => [
                'first' => $paginatedStores->url(1),
                'last' => $paginatedStores->url($paginatedStores->lastPage()),
                'prev' => $paginatedStores->previousPageUrl(),
                'next' => $paginatedStores->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Get a single store.
     * 
     * Retrieve detailed information about a specific store by its ID.
     * 
     * <aside class="notice">
     * Only active stores that receive online orders can be retrieved. If the store is inactive, deleted, or doesn't receive online orders, a 404 error will be returned.
     * </aside>
     * 
     * @urlParam id int required The store ID. Example: 1
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "name": "Downtown Store",
     *     "name_localized": {
     *       "en": "Downtown Store",
     *       "ar": "متجر وسط المدينة"
     *     },
     *     "address": "123 Main Street, Riyadh",
     *     "latitude": "24.71360000",
     *     "longitude": "46.67530000",
     *     "open_time": "09:00",
     *     "close_time": "22:00",
     *     "is_open_now": true
     *   }
     * }
     * 
     * @response 404 {
     *   "success": false,
     *   "error": {
     *     "code": "STORE_NOT_FOUND",
     *     "message": "Store not found."
     *   }
     * }
     * 
     * @responseField id int The store ID.
     * @responseField name string The store name.
     * @responseField name_localized object Localized store names (e.g., {"en": "Store Name", "ar": "اسم المتجر"}).
     * @responseField address string The store address.
     * @responseField latitude string The store latitude coordinate.
     * @responseField longitude string The store longitude coordinate.
     * @responseField open_time string Store opening time (HH:mm format).
     * @responseField close_time string Store closing time (HH:mm format).
     * @responseField is_open_now boolean Whether the store is currently open.
     */
    public function show(int $id): JsonResponse
    {
        $store = Store::activeForOrders()->find($id);

        if (!$store) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STORE_NOT_FOUND',
                    'message' => 'Store not found.',
                ],
            ], 404);
        }

        $store->is_open_now = $store->isOpenNow();

        return response()->json([
            'success' => true,
            'data' => new StoreResource($store),
        ]);
    }
}
