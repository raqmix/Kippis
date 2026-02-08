<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\CategoryRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Catalog APIs
 */
class CategoryController extends Controller
{
    public function __construct(
        private CategoryRepository $categoryRepository
    ) {
    }

    /**
     * Get list of categories
     *
     * @queryParam source string optional Filter by source (all, foodics, manual). Default: "all". Example: "foodics"
     * @queryParam is_active string optional Filter by active status (0, 1). Default: "1". Example: "1"
     * @queryParam q string optional Search query. Example: "drinks"
     * @queryParam sort_by string optional Sort field. Default: "sort_order". Example: "name"
     * @queryParam sort_order string optional Sort order (asc, desc). Default: "asc". Example: "asc"
     * @queryParam per_page integer optional Items per page (max 100). Default: 15. Example: 20
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Beverages",
     *       "image": "https://example.com/image.jpg"
     *     }
     *   ],
     *   "pagination": {
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 50,
     *     "last_page": 4
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'source' => $request->query('source', 'all'),
            'is_active' => $request->query('is_active', '1'),
            'q' => $request->query('q'),
            'sort_by' => $request->query('sort_by', 'sort_order'),
            'sort_order' => $request->query('sort_order', 'asc'),
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
