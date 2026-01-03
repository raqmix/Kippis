<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\ProductRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Catalog APIs
 */
class ProductController extends Controller
{
    public function __construct(
        private ProductRepository $productRepository
    ) {
    }

    /**
     * Get list of products
     *
     * @queryParam store_id integer optional Filter by store ID. Example: 1
     * @queryParam category_id integer optional Filter by category ID. Example: 2
     * @queryParam q string optional Search query. Example: "pizza"
     * @queryParam source string optional Filter by source (all, foodics, manual). Default: "all". Example: "foodics"
     * @queryParam is_active string optional Filter by active status (0, 1). Default: "1". Example: "1"
     * @queryParam price_min number optional Minimum price filter. Example: 10.50
     * @queryParam price_max number optional Maximum price filter. Example: 100.00
     * @queryParam sort_by string optional Sort field. Default: "created_at". Example: "price"
     * @queryParam sort_order string optional Sort order (asc, desc). Default: "desc". Example: "asc"
     * @queryParam per_page integer optional Items per page (max 100). Default: 15. Example: 20
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Product Name",
     *       "price": 25.50
     *     }
     *   ],
     *   "pagination": {
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 100,
     *     "last_page": 7
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'store_id' => $request->query('store_id'),
            'category_id' => $request->query('category_id'),
            'q' => $request->query('q'),
            'source' => $request->query('source', 'all'),
            'is_active' => $request->query('is_active', '1'),
            'price_min' => $request->query('price_min'),
            'price_max' => $request->query('price_max'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_order' => $request->query('sort_order', 'desc'),
        ];

        $perPage = min($request->query('per_page', 15), 100);
        $products = $this->productRepository->getPaginated($filters, $perPage);

        return apiSuccess(
            ProductResource::collection($products),
            null,
            200,
            [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ]
        );
    }

    /**
     * Get single product by ID
     *
     * Returns product details including allowed addons (modifiers assigned to this product).
     * Addons can be selected when adding the product to cart.
     *
     * @urlParam id required The ID of the product. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "name": "Product Name",
     *     "base_price": 25.50,
     *     "description": "Product description",
     *     "allowed_addons": [
     *       {
     *         "id": 5,
     *         "modifier_id": 5,
     *         "name": "Extra Sweetness",
     *         "type": "sweetness",
     *         "max_level": 5,
     *         "price": 1.50,
     *         "is_required": false,
     *         "min_select": 0,
     *         "max_select": 5
     *       }
     *     ]
     *   }
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "error": "PRODUCT_NOT_FOUND",
     *   "message": "product_not_found"
     * }
     */
    public function show($id): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            return apiError('PRODUCT_NOT_FOUND', 'product_not_found', 404);
        }

        return apiSuccess(new ProductResource($product));
    }
}
