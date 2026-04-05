<?php

namespace App\Http\Controllers\Api\V1\Kiosk;

use App\Core\Models\PaymentMethod;
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
        $codes = ['cash', 'card'];
        $methods = PaymentMethod::with('channel')
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn ($m) => array_search($m->code, $codes))
            ->values();

        return apiSuccess(PaymentMethodResource::collection($methods));
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

