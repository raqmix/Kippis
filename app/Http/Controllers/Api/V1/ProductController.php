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

    public function show($id): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            return apiError('PRODUCT_NOT_FOUND', 'product_not_found', 404);
        }

        return apiSuccess(new ProductResource($product));
    }
}
