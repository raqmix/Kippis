<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\PageRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group CMS Pages APIs
 */
class PageController extends Controller
{
    public function __construct(
        private PageRepository $pageRepository
    ) {
    }

    /**
     * Get list of CMS pages.
     * 
     * Retrieve a paginated list of CMS pages (FAQ, Terms, Privacy, etc.).
     * 
     * @queryParam type string optional Filter by page type. Options: `faq`, `terms`, `privacy`. Example: faq
     * @queryParam is_active string optional Filter by active status. Options: `1` (active), `0` (inactive). Default: `1`. Example: 1
     * @queryParam q string optional Search in title or content. Example: privacy
     * @queryParam sort_by string optional Sort field. Options: `created_at`, `slug`, `type`, `updated_at`. Default: `created_at`. Example: slug
     * @queryParam sort_order string optional Sort order. Options: `asc`, `desc`. Default: `desc`. Example: asc
     * @queryParam page int optional Page number. Default: 1. Example: 1
     * @queryParam per_page int optional Items per page. Default: 15. Maximum: 100. Example: 15
     * 
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "slug": "privacy-policy",
     *       "type": "privacy",
     *       "is_active": true,
     *       "version": 1,
     *       "title": "Privacy Policy",
     *       "content": "Our privacy policy content...",
     *       "title_ar": "سياسة الخصوصية",
     *       "content_ar": "محتوى سياسة الخصوصية...",
     *       "title_en": "Privacy Policy",
     *       "content_en": "Our privacy policy content...",
     *       "created_at": "2024-01-15T10:30:00.000000Z",
     *       "updated_at": "2024-01-15T10:30:00.000000Z"
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 1,
     *     "last_page": 1
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'type' => $request->query('type'),
            'is_active' => $request->query('is_active', '1'),
            'q' => $request->query('q'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_order' => $request->query('sort_order', 'desc'),
        ];

        $perPage = min($request->query('per_page', 15), 100);
        $pages = $this->pageRepository->getPaginated($filters, $perPage);

        return apiSuccess(
            PageResource::collection($pages),
            null,
            200,
            [
                'current_page' => $pages->currentPage(),
                'per_page' => $pages->perPage(),
                'total' => $pages->total(),
                'last_page' => $pages->lastPage(),
            ]
        );
    }

    /**
     * Get a single CMS page by ID.
     * 
     * @urlParam id int required The page ID. Example: 1
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "slug": "privacy-policy",
     *     "type": "privacy",
     *     "is_active": true,
     *     "version": 1,
     *     "title": "Privacy Policy",
     *     "content": "Our privacy policy content...",
     *     "title_ar": "سياسة الخصوصية",
     *     "content_ar": "محتوى سياسة الخصوصية...",
     *     "title_en": "Privacy Policy",
     *     "content_en": "Our privacy policy content...",
     *     "created_at": "2024-01-15T10:30:00.000000Z",
     *     "updated_at": "2024-01-15T10:30:00.000000Z"
     *   }
     * }
     * 
     * @response 404 {
     *   "success": false,
     *   "error": {
     *     "code": "PAGE_NOT_FOUND",
     *     "message": "Page not found."
     *   }
     * }
     */
    public function show($id): JsonResponse
    {
        $page = $this->pageRepository->findById($id);

        if (!$page || !$page->is_active) {
            return apiError('PAGE_NOT_FOUND', 'page_not_found', 404);
        }

        return apiSuccess(new PageResource($page));
    }

    /**
     * Get a CMS page by slug.
     * 
     * Retrieve a CMS page by its slug (e.g., "privacy-policy", "terms-of-service").
     * 
     * @urlParam slug string required The page slug. Example: privacy-policy
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "slug": "privacy-policy",
     *     "type": "privacy",
     *     "is_active": true,
     *     "version": 1,
     *     "title": "Privacy Policy",
     *     "content": "Our privacy policy content...",
     *     "title_ar": "سياسة الخصوصية",
     *     "content_ar": "محتوى سياسة الخصوصية...",
     *     "title_en": "Privacy Policy",
     *     "content_en": "Our privacy policy content...",
     *     "created_at": "2024-01-15T10:30:00.000000Z",
     *     "updated_at": "2024-01-15T10:30:00.000000Z"
     *   }
     * }
     * 
     * @response 404 {
     *   "success": false,
     *   "error": {
     *     "code": "PAGE_NOT_FOUND",
     *     "message": "Page not found."
     *   }
     * }
     */
    public function showBySlug(string $slug): JsonResponse
    {
        $page = $this->pageRepository->findBySlug($slug);

        if (!$page) {
            return apiError('PAGE_NOT_FOUND', 'page_not_found', 404);
        }

        return apiSuccess(new PageResource($page));
    }

    /**
     * Get pages by type.
     * 
     * Retrieve all active pages of a specific type (FAQ, Terms, Privacy).
     * 
     * @urlParam type string required The page type. Options: `faq`, `terms`, `privacy`. Example: faq
     * 
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "slug": "faq-general",
     *       "type": "faq",
     *       "is_active": true,
     *       "version": 1,
     *       "title": "General Questions",
     *       "content": "FAQ content...",
     *       "title_ar": "أسئلة عامة",
     *       "content_ar": "محتوى الأسئلة...",
     *       "title_en": "General Questions",
     *       "content_en": "FAQ content...",
     *       "created_at": "2024-01-15T10:30:00.000000Z",
     *       "updated_at": "2024-01-15T10:30:00.000000Z"
     *     }
     *   ]
     * }
     */
    public function getByType(string $type): JsonResponse
    {
        $pages = $this->pageRepository->getByType($type);

        return apiSuccess(PageResource::collection($pages));
    }
}

