<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\LoyaltyWalletRepository;
use App\Core\Repositories\QrReceiptRepository;
use App\Helpers\FileHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\QrReceiptResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group QR Receipts APIs
 */
class QrReceiptController extends Controller
{
    public function __construct(
        private QrReceiptRepository $qrReceiptRepository,
        private LoyaltyWalletRepository $loyaltyWalletRepository,
        private FileHelper $fileHelper
    ) {
    }

    /**
     * Scan QR receipt with image
     * 
     * @authenticated
     * 
     * @bodyParam receipt_image file required Receipt image file (max 5MB, formats: jpg, jpeg, png, gif). No-example
     * @bodyParam receipt_number string required Receipt number. Example: "RCP-123456"
     * @bodyParam amount number required Receipt amount (min 0). Example: 50.00
     * @bodyParam points_requested integer required Points requested (min 1). Example: 50
     * @bodyParam store_id integer optional Store ID. Example: 1
     * @bodyParam meta array optional Additional metadata. Example: {"notes": "Special order"}
     * 
     * @response 201 {
     *   "success": true,
     *   "message": "receipt_submitted",
     *   "data": {
     *     "id": 1,
     *     "receipt_number": "RCP-123456",
     *     "amount": 50.00,
     *     "points_requested": 50,
     *     "status": "pending"
     *   }
     * }
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'receipt_image' => 'required|image|max:5120',
            'receipt_number' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'points_requested' => 'required|integer|min:1',
            'meta' => 'nullable|array',
        ]);

        $customer = auth('api')->user();
        $imagePath = $this->fileHelper->uploadImage($request->file('receipt_image'), 'qr_receipts', 'public', 5120);

        $receipt = $this->qrReceiptRepository->create([
            'customer_id' => $customer->id,
            'store_id' => $request->input('store_id'),
            'receipt_number' => $request->input('receipt_number'),
            'receipt_image' => $imagePath,
            'amount' => $request->input('amount'),
            'points_requested' => $request->input('points_requested'),
            'meta' => $request->input('meta'),
            'scanned_at' => now(),
            'status' => 'pending',
        ]);

        return apiSuccess(new QrReceiptResource($receipt), 'receipt_submitted', 201);
    }

    /**
     * Submit receipt manually (without image)
     * 
     * @authenticated
     * 
     * @bodyParam receipt_number string required Receipt number. Example: "RCP-123456"
     * @bodyParam amount number required Receipt amount (min 0). Example: 50.00
     * @bodyParam points_requested integer required Points requested (min 1). Example: 50
     * @bodyParam store_id integer optional Store ID. Example: 1
     * @bodyParam meta array optional Additional metadata. Example: {"notes": "Special order"}
     * 
     * @response 201 {
     *   "success": true,
     *   "message": "receipt_submitted",
     *   "data": {
     *     "id": 1,
     *     "receipt_number": "RCP-123456",
     *     "amount": 50.00,
     *     "points_requested": 50,
     *     "status": "pending"
     *   }
     * }
     */
    public function manual(Request $request): JsonResponse
    {
        $request->validate([
            'receipt_number' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'points_requested' => 'required|integer|min:1',
            'store_id' => 'nullable|exists:stores,id',
            'meta' => 'nullable|array',
        ]);

        $customer = auth('api')->user();

        $receipt = $this->qrReceiptRepository->create([
            'customer_id' => $customer->id,
            'store_id' => $request->input('store_id'),
            'receipt_number' => $request->input('receipt_number'),
            'amount' => $request->input('amount'),
            'points_requested' => $request->input('points_requested'),
            'meta' => $request->input('meta'),
            'status' => 'pending',
        ]);

        return apiSuccess(new QrReceiptResource($receipt), 'receipt_submitted', 201);
    }

    /**
     * Get receipt history
     * 
     * @authenticated
     * 
     * @queryParam per_page integer optional Items per page (max 100). Default: 15. Example: 20
     * 
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "receipt_number": "RCP-123456",
     *       "amount": 50.00,
     *       "points_requested": 50,
     *       "status": "approved"
     *     }
     *   ],
     *   "pagination": {
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 10,
     *     "last_page": 1
     *   }
     * }
     */
    public function history(Request $request): JsonResponse
    {
        $customer = auth('api')->user();
        $perPage = min($request->query('per_page', 15), 100);

        $receipts = $this->qrReceiptRepository->getPaginatedForCustomer($customer->id, $perPage);

        return apiSuccess(
            QrReceiptResource::collection($receipts),
            null,
            200,
            [
                'current_page' => $receipts->currentPage(),
                'per_page' => $receipts->perPage(),
                'total' => $receipts->total(),
                'last_page' => $receipts->lastPage(),
            ]
        );
    }
}
