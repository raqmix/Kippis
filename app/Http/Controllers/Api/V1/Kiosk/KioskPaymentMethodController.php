<?php

namespace App\Http\Controllers\Api\V1\Kiosk;

use App\Core\Repositories\PaymentMethodRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PaymentMethodResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Kiosk Payment Methods APIs
 */
class KioskPaymentMethodController extends Controller
{
    public function __construct(
        private PaymentMethodRepository $paymentMethodRepository
    ) {
    }

    /**
     * Get list of payment methods available for kiosk
     *
     * @queryParam is_active string optional Filter by active status. Options: `1` (active), `0` (inactive). Default: `1`. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Cash",
     *       "code": "cash",
     *       "is_active": true
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'is_active' => $request->query('is_active', '1'),
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
     * Get a single payment method by ID
     *
     * @urlParam id int required The payment method ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "name": "Cash",
     *     "code": "cash",
     *     "is_active": true
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

