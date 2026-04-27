<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(private ReferralService $service) {}

    /**
     * GET /api/v1/referral/code — get or generate the customer's referral code
     */
    public function code(Request $request): JsonResponse
    {
        $customer = auth('api')->user();
        return apiSuccess($this->service->getOrCreateCode($customer));
    }

    /**
     * POST /api/v1/referral/apply — apply a referral code (after registration)
     */
    public function apply(Request $request): JsonResponse
    {
        $data     = $request->validate(['code' => ['required', 'string', 'max:30']]);
        $customer = auth('api')->user();

        try {
            $this->service->applyReferralCode($customer, $data['code']);
        } catch (\RuntimeException $e) {
            return apiError('REFERRAL_DISABLED', $e->getMessage(), 403);
        } catch (\DomainException $e) {
            return apiError('REFERRAL_ERROR', $e->getMessage(), 422);
        }

        return apiSuccess(['message' => 'Referral applied. Points have been awarded.']);
    }
}
