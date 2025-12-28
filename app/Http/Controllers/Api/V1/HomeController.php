<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\CategoryRepository;
use App\Core\Repositories\ProductRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private ProductRepository $productRepository
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        // Get active categories
        $categories = $this->categoryRepository->getAllActive();
        $categoriesResource = CategoryResource::collection($categories);

        // Get featured products
        $products = $this->productRepository->getAllActive();
        $productsResource = ProductResource::collection($products->take(10));

        return apiSuccess([
            'categories' => $categoriesResource,
            'featured_products' => $productsResource,
        ]);
    }
}
