<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\LoyaltyWalletRepository;
use App\Core\Repositories\PromoQrCodeRepository;
use App\Core\Models\PromoQrCodeScan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ScanPromoQrRequest;
use App\Http\Resources\Api\V1\PromoQrScanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @group Promotional QR Codes APIs
 */
class PromoQrController extends Controller
{
    public function __construct(
        private PromoQrCodeRepository $qrCodeRepository,
        private LoyaltyWalletRepository $loyaltyWalletRepository
    ) {
    }

    /**
     * Scan promotional QR code
     *
     * Scan a promotional QR code to receive free loyalty points. The QR code must be active, within validity dates, and not exceed usage limits.
     *
     * @authenticated
     *
     * @header Authorization Bearer {token} JWT token obtained from login
     *
     * @bodyParam code string required The QR code string to scan. Example: "PROMO-ABC123"
     *
     * @response 200 {
     *   "success": true,
     *   "message": "qr_code_scanned",
     *   "data": {
     *     "id": 1,
     *     "code": "PROMO-ABC123",
     *     "name": "Welcome Bonus",
     *     "points_awarded": 50,
     *     "scanned_at": "2026-01-03T18:30:00Z"
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
     *   "error": "QR_CODE_EXPIRED",
     *   "message": "QR code has expired."
     * }
     *
     * @response 400 {
     *   "success": false,
     *   "error": "QR_CODE_NOT_AVAILABLE",
     *   "message": "QR code is not yet available."
     * }
     *
     * @response 400 {
     *   "success": false,
     *   "error": "QR_CODE_LIMIT_EXCEEDED",
     *   "message": "You have exceeded the maximum uses for this QR code."
     * }
     *
     * @response 400 {
     *   "success": false,
     *   "error": "QR_CODE_TOTAL_LIMIT_EXCEEDED",
     *   "message": "QR code has reached its maximum total uses."
     * }
     *
     * @param ScanPromoQrRequest $request
     * @return JsonResponse
     */
    public function scan(ScanPromoQrRequest $request): JsonResponse
    {
        $customer = auth('api')->user();

        if (!$customer) {
            return apiError('UNAUTHORIZED', 'unauthorized', 401);
        }

        $code = $request->validated()['code'];
        $qrCode = $this->qrCodeRepository->findByCode($code);

        if (!$qrCode) {
            return apiError('QR_CODE_NOT_FOUND', 'qr_code_not_found', 400);
        }

        // Validate QR code status
        if (!$qrCode->isActive()) {
            return apiError('QR_CODE_INACTIVE', 'qr_code_inactive', 400);
        }

        if ($qrCode->isExpired()) {
            return apiError('QR_CODE_EXPIRED', 'qr_code_expired', 400);
        }

        if (now()->isBefore($qrCode->available_from)) {
            return apiError('QR_CODE_NOT_AVAILABLE', 'qr_code_not_available', 400);
        }

        // Check per-customer limit
        if ($qrCode->max_uses_per_customer !== null) {
            $customerUsageCount = $this->qrCodeRepository->getUsageCountForCustomer($qrCode, $customer->id);
            if ($customerUsageCount >= $qrCode->max_uses_per_customer) {
                return apiError('QR_CODE_LIMIT_EXCEEDED', 'qr_code_limit_exceeded', 400);
            }
        }

        // Check total limit
        if ($qrCode->max_total_uses !== null && $qrCode->total_uses_count >= $qrCode->max_total_uses) {
            return apiError('QR_CODE_TOTAL_LIMIT_EXCEEDED', 'qr_code_total_limit_exceeded', 400);
        }

        // Process scan in transaction
        try {
            DB::beginTransaction();

            // Create scan record
            $scan = PromoQrCodeScan::create([
                'promo_qr_code_id' => $qrCode->id,
                'customer_id' => $customer->id,
                'points_awarded' => $qrCode->points,
                'scanned_at' => now(),
            ]);

            // Increment total usage count
            $this->qrCodeRepository->incrementUsageCount($qrCode);

            // Award points to customer's loyalty wallet
            $wallet = $this->loyaltyWalletRepository->getOrCreateForCustomer($customer->id);
            $this->loyaltyWalletRepository->addPoints(
                $wallet,
                $qrCode->points,
                'earned',
                "Points from promotional QR code: {$qrCode->name}",
                'promo_qr_code',
                $qrCode->id
            );

            DB::commit();

            return apiSuccess(
                new PromoQrScanResource($scan),
                'qr_code_scanned'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Promo QR code scan failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return apiError('SCAN_FAILED', 'scan_failed', 500);
        }
    }
}

