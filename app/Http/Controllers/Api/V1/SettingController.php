<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\SettingRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SettingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Settings APIs
 */
class SettingController extends Controller
{
    public function __construct(
        private SettingRepository $settingRepository
    ) {
    }

    /**
     * Get all settings grouped by group.
     * 
     * Retrieve all settings organized by their group (general, payment, notifications, etc.).
     * 
     * @queryParam group string optional Filter by specific group. Example: general
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "general": {
     *       "app_name": "Kippis",
     *       "app_version": "1.0.0"
     *     },
     *     "payment": {
     *       "currency": "SAR",
     *       "tax_rate": 15
     *     }
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'group' => $request->query('group'),
        ];

        $settings = $this->settingRepository->getAllGrouped($filters);

        return apiSuccess($settings);
    }

    /**
     * Get settings by group.
     * 
     * Retrieve all settings for a specific group.
     * 
     * @urlParam group string required The settings group. Example: general
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "app_name": "Kippis",
     *     "app_version": "1.0.0",
     *     "support_email": "support@kippis.com"
     *   }
     * }
     * 
     * @response 404 {
     *   "success": false,
     *   "error": {
     *     "code": "GROUP_NOT_FOUND",
     *     "message": "Settings group not found."
     *   }
     * }
     */
    public function getByGroup(string $group): JsonResponse
    {
        $settings = $this->settingRepository->getByGroup($group);

        if (empty($settings)) {
            return apiError('GROUP_NOT_FOUND', 'settings_group_not_found', 404);
        }

        return apiSuccess($settings);
    }

    /**
     * Get setting by key.
     * 
     * Retrieve a specific setting value by its key.
     * 
     * @urlParam key string required The setting key. Example: app_name
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "key": "app_name",
     *     "value": "Kippis",
     *     "type": "string",
     *     "group": "general"
     *   }
     * }
     * 
     * @response 404 {
     *   "success": false,
     *   "error": {
     *     "code": "SETTING_NOT_FOUND",
     *     "message": "Setting not found."
     *   }
     * }
     */
    public function getByKey(string $key): JsonResponse
    {
        $setting = $this->settingRepository->getByKey($key);

        if ($setting === null) {
            return apiError('SETTING_NOT_FOUND', 'setting_not_found', 404);
        }

        // Get the full setting model for the resource
        $settingModel = \App\Core\Models\Setting::where('key', $key)->first();
        
        if (!$settingModel) {
            return apiError('SETTING_NOT_FOUND', 'setting_not_found', 404);
        }

        return apiSuccess(new SettingResource($settingModel));
    }

    /**
     * Get multiple settings by keys.
     * 
     * Retrieve multiple settings by providing an array of keys.
     * 
     * @bodyParam keys array required Array of setting keys. Example: ["app_name", "app_version", "support_email"]
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "app_name": "Kippis",
     *     "app_version": "1.0.0",
     *     "support_email": "support@kippis.com"
     *   }
     * }
     */
    public function getByKeys(Request $request): JsonResponse
    {
        $request->validate([
            'keys' => 'required|array',
            'keys.*' => 'required|string',
        ]);

        $settings = $this->settingRepository->getByKeys($request->input('keys'));

        return apiSuccess($settings);
    }

    /**
     * Get paginated settings list.
     * 
     * Retrieve a paginated list of all settings with filters.
     * 
     * @queryParam group string optional Filter by group. Example: general
     * @queryParam type string optional Filter by type. Options: `string`, `boolean`, `json`, `number`, `integer`, `float`, `decimal`. Example: string
     * @queryParam q string optional Search in key or value. Example: app
     * @queryParam sort_by string optional Sort field. Options: `key`, `group`, `type`, `created_at`, `updated_at`. Default: `key`. Example: key
     * @queryParam sort_order string optional Sort order. Options: `asc`, `desc`. Default: `asc`. Example: asc
     * @queryParam page int optional Page number. Default: 1. Example: 1
     * @queryParam per_page int optional Items per page. Default: 15. Maximum: 100. Example: 15
     * 
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "key": "app_name",
     *       "value": "Kippis",
     *       "type": "string",
     *       "group": "general",
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
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'group' => $request->query('group'),
            'type' => $request->query('type'),
            'q' => $request->query('q'),
            'sort_by' => $request->query('sort_by', 'key'),
            'sort_order' => $request->query('sort_order', 'asc'),
        ];

        $perPage = min($request->query('per_page', 15), 100);
        $settings = $this->settingRepository->getPaginated($filters, $perPage);

        return apiSuccess(
            SettingResource::collection($settings),
            null,
            200,
            [
                'current_page' => $settings->currentPage(),
                'per_page' => $settings->perPage(),
                'total' => $settings->total(),
                'last_page' => $settings->lastPage(),
            ]
        );
    }
}

