<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\PaymentMethodRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PaymentMethodResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Payment Methods APIs
 */
class PaymentMethodController extends Controller
{
    public function __construct(
        private PaymentMethodRepository $paymentMethodRepository
    ) {
    }

    /**
     * Get list of payment methods.
     * 
     * Retrieve a paginated list of active payment methods available for checkout.
     * 
     * @queryParam is_active string optional Filter by active status. Options: `1` (active), `0` (inactive). Default: `1`. Example: 1
     * @queryParam channel_id int optional Filter by channel ID. Example: 1
     * @queryParam q string optional Search by name or code. Example: cash
     * @queryParam sort_by string optional Sort field. Options: `created_at`, `name`, `code`, `updated_at`. Default: `created_at`. Example: name
     * @queryParam sort_order string optional Sort order. Options: `asc`, `desc`. Default: `desc`. Example: asc
     * @queryParam page int optional Page number. Default: 1. Example: 1
     * @queryParam per_page int optional Items per page. Default: 15. Maximum: 100. Example: 15
     * 
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Cash",
     *       "code": "cash",
     *       "is_active": true,
     *       "channel": null,
     *       "created_at": "2024-01-15T10:30:00.000000Z",
     *       "updated_at": "2024-01-15T10:30:00.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "name": "Card",
     *       "code": "card",
     *       "is_active": true,
     *       "channel": {
     *         "id": 1,
     *         "name": "Payment Gateway"
     *       },
     *       "created_at": "2024-01-15T10:30:00.000000Z",
     *       "updated_at": "2024-01-15T10:30:00.000000Z"
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 2,
     *     "last_page": 1
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'is_active' => $request->query('is_active', '1'),
            'channel_id' => $request->query('channel_id'),
            'q' => $request->query('q'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_order' => $request->query('sort_order', 'desc'),
        ];

        $perPage = min($request->query('per_page', 15), 100);
        $paymentMethods = $this->paymentMethodRepository->getPaginated($filters, $perPage);

        return apiSuccess(
            PaymentMethodResource::collection($paymentMethods),
            null,
            200,
            [
                'current_page' => $paymentMethods->currentPage(),
                'per_page' => $paymentMethods->perPage(),
                'total' => $paymentMethods->total(),
                'last_page' => $paymentMethods->lastPage(),
            ]
        );
    }

    /**
     * Get a single payment method.
     * 
     * Retrieve detailed information about a specific payment method by its ID.
     * 
     * @urlParam id int required The payment method ID. Example: 1
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "name": "Cash",
     *     "code": "cash",
     *     "is_active": true,
     *     "channel": null,
     *     "configuration": null,
     *     "created_at": "2024-01-15T10:30:00.000000Z",
     *     "updated_at": "2024-01-15T10:30:00.000000Z"
     *   }
     * }
     * 
     * @response 404 {
     *   "success": false,
     *   "error": {
     *     "code": "PAYMENT_METHOD_NOT_FOUND",
     *     "message": "Payment method not found."
     *   }
     * }
     */
    public function show($id): JsonResponse
    {
        $paymentMethod = $this->paymentMethodRepository->findById($id);

        if (!$paymentMethod) {
            return apiError('PAYMENT_METHOD_NOT_FOUND', 'payment_method_not_found', 404);
        }

        return apiSuccess(new PaymentMethodResource($paymentMethod));
    }
}

