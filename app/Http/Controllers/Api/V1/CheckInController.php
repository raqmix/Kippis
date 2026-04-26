<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CheckInService;
use Illuminate\Http\JsonResponse;

class CheckInController extends Controller
{
    public function __construct(private readonly CheckInService $checkInService) {}

    /**
     * POST /api/v1/check-in
     */
    public function store(): JsonResponse
    {
        $customer = auth('api')->user();

        try {
            $result = $this->checkInService->checkIn($customer);
        } catch (\DomainException) {
            return apiError('ALREADY_CHECKED_IN', 'Already checked in today.', 409);
        } catch (\RuntimeException $e) {
            return apiError('CHECK_IN_DISABLED', $e->getMessage(), 403);
        }

        return apiSuccess(['data' => $result], 200);
    }

    /**
     * GET /api/v1/check-in/status
     */
    public function status(): JsonResponse
    {
        $customer = auth('api')->user();
        $status   = $this->checkInService->getStreakStatus($customer);

        return apiSuccess(['data' => $status]);
    }
}
