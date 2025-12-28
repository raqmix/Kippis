<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\LoyaltyWalletRepository;
use App\Core\Repositories\QrReceiptRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\QrReceiptResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrReceiptController extends Controller
{
    public function __construct(
        private QrReceiptRepository $qrReceiptRepository,
        private LoyaltyWalletRepository $loyaltyWalletRepository
    ) {
    }

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
        $imagePath = $request->file('receipt_image')->store('qr_receipts', 'public');

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
