<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\CategoryRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private CategoryRepository $categoryRepository
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'source' => $request->query('source', 'all'),
        ];

        $perPage = min($request->query('per_page', 15), 100);
        $categories = $this->categoryRepository->getPaginated($filters, $perPage);

        return apiSuccess(
            CategoryResource::collection($categories),
            null,
            200,
            [
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'last_page' => $categories->lastPage(),
            ]
        );
    }
}
