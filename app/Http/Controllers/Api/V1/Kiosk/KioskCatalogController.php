<?php

namespace App\Http\Controllers\Api\V1\Kiosk;

use App\Core\Repositories\CategoryRepository;
use App\Core\Repositories\ProductRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Kiosk Catalog APIs
 */
class KioskCatalogController extends Controller
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private ProductRepository $productRepository
    ) {
    }

    /**
     * Get list of categories for the authenticated store
     *
     * @queryParam q string optional Search query. Example: "drinks"
     * @queryParam sort_by string optional Sort field. Default: "created_at". Example: "name"
     * @queryParam sort_order string optional Sort order (asc, desc). Default: "desc". Example: "asc"
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Beverages",
     *       "image": "https://example.com/image.jpg"
     *     }
     *   ]
     * }
     */
    public function categories(Request $request): JsonResponse
    {
        $store = $request->attributes->get('kiosk_store');
        
        $filters = [
            'source' => 'all',
            'is_active' => '1',
            'q' => $request->query('q'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_order' => $request->query('sort_order', 'desc'),
        ];

        $categories = $this->categoryRepository->getAll($filters);

        return apiSuccess(CategoryResource::collection($categories));
    }

    /**
     * Get list of products for the authenticated store
     *
     * @queryParam category_id integer optional Filter by category ID. Example: 2
     * @queryParam q string optional Search query. Example: "pizza"
     * @queryParam price_min number optional Minimum price filter. Example: 10.50
     * @queryParam price_max number optional Maximum price filter. Example: 100.00
     * @queryParam sort_by string optional Sort field. Default: "created_at". Example: "price"
     * @queryParam sort_order string optional Sort order (asc, desc). Default: "desc". Example: "asc"
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Product Name",
     *       "price": 25.50
     *     }
     *   ]
     * }
     */
    public function products(Request $request): JsonResponse
    {
        $store = $request->attributes->get('kiosk_store');
        
        $filters = [
            'store_id' => $store->id,
            'category_id' => $request->query('category_id'),
            'q' => $request->query('q'),
            'source' => 'all',
            'is_active' => '1',
            'price_min' => $request->query('price_min'),
            'price_max' => $request->query('price_max'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_order' => $request->query('sort_order', 'desc'),
        ];

        $products = $this->productRepository->getAll($filters);

        return apiSuccess(ProductResource::collection($products));
    }

    /**
     * Get single product by ID for the authenticated store
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
     *     "allowed_addons": []
     *   }
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "error": "PRODUCT_NOT_FOUND",
     *   "message": "product_not_found"
     * }
     */
    public function product($id, Request $request): JsonResponse
    {
        $store = $request->attributes->get('kiosk_store');
        
        $product = $this->productRepository->findById($id);

        if (!$product) {
            return apiError('PRODUCT_NOT_FOUND', 'product_not_found', 404);
        }

        // Verify product belongs to the authenticated store (if store filtering exists)
        // Note: Adjust this based on your product-store relationship
        // If products are global, remove this check

        return apiSuccess(new ProductResource($product));
    }

    /**
     * Get home page data for the authenticated store
     *
     * Returns active categories and featured products for the kiosk home page.
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "categories": [
     *       {
     *         "id": 1,
     *         "name": "Category Name",
     *         "image": "https://example.com/image.jpg"
     *       }
     *     ],
     *     "featured_products": [
     *       {
     *         "id": 1,
     *         "name": "Product Name",
     *         "price": 25.50
     *       }
     *     ]
     *   }
     * }
     */
    public function home(Request $request): JsonResponse
    {
        $store = $request->attributes->get('kiosk_store');
        
        // Get active categories
        $categories = $this->categoryRepository->getAllActive();
        $categoriesResource = CategoryResource::collection($categories);

        // Get featured products for the store
        $filters = [
            'store_id' => $store->id,
            'is_active' => '1',
            'source' => 'all',
        ];
        $products = $this->productRepository->getAll($filters);
        $productsResource = ProductResource::collection($products->take(10));

        return apiSuccess([
            'categories' => $categoriesResource,
            'featured_products' => $productsResource,
        ]);
    }
}

