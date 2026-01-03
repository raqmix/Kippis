<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ScanQrCodeRequest;
use App\Http\Resources\Api\V1\QrCodeRedemptionResource;
use App\Services\QrCodeRedemptionService;
use Illuminate\Http\JsonResponse;

/**
 * @group QR Codes APIs
 */
class QrReceiptController extends Controller
{
    public function __construct(
        private QrCodeRedemptionService $qrCodeRedemptionService
    ) {
    }

    /**
     * Scan QR code
     * 
     * Scan a QR code to redeem points. The QR code must be active, within validity dates, and not exceed usage limits.
     * 
     * @authenticated
     * 
     * @bodyParam code string required The QR code string to scan. Example: "QR-ABC123"
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "QR code redeemed successfully.",
     *   "data": {
     *     "qr_code": {
     *       "id": 1,
     *       "code": "QR-ABC123",
     *       "title": "Welcome Bonus",
     *       "points_awarded": 50
     *     },
     *     "usage": {
     *       "id": 1,
     *       "used_at": "2026-01-03T18:30:00Z"
     *     },
     *     "remaining_limits": {
     *       "total": 99,
     *       "per_customer": 4
     *     }
     *   }
     * }
     * 
     * @response 400 {
     *   "success": false,
     *   "error": "QR_CODE_NOT_FOUND",
     *   "message": "QR code not found."
     * }
     * 
     * @response 400 {
     *   "success": false,
     *   "error": "QR_CODE_INACTIVE",
     *   "message": "QR code is not active."
     * }
     * 
     * @response 400 {
     *   "success": false,
     *   "error": "QR_CODE_NOT_STARTED",
     *   "message": "QR code has not started yet."
     * }
     * 
     * @response 400 {
     *   "success": false,
     *   "error": "QR_CODE_EXPIRED",
     *   "message": "QR code has expired."
     * }
     * 
     * @response 400 {
     *   "success": false,
     *   "error": "QR_CODE_PER_CUSTOMER_LIMIT_EXCEEDED",
     *   "message": "You have reached the maximum uses for this QR code."
     * }
     * 
     * @response 400 {
     *   "success": false,
     *   "error": "QR_CODE_TOTAL_LIMIT_EXCEEDED",
     *   "message": "QR code has reached its total usage limit."
     * }
     */
    public function scan(ScanQrCodeRequest $request): JsonResponse
    {
        $customer = auth('api')->user();

        if (!$customer) {
            return apiError('UNAUTHORIZED', 'unauthorized', 401);
        }

        $code = $request->validated()['code'];
        $result = $this->qrCodeRedemptionService->redeem($customer, $code);

        if (!$result['success']) {
            return apiError(
                $result['error_code'] ?? 'REDEMPTION_FAILED',
                $result['message'] ?? 'Redemption failed',
                400
            );
        }

        return apiSuccess(
            new QrCodeRedemptionResource($result['data']),
            $result['message']
        );
    }

}
