<?php

namespace App\Core\Repositories;

use App\Core\Models\QrReceipt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QrReceiptRepository
{
    /**
     * Create a new QR receipt.
     */
    public function create(array $data): QrReceipt
    {
        return QrReceipt::create($data);
    }

    /**
     * Get paginated receipts for customer.
     */
    public function getPaginatedForCustomer(int $customerId, int $perPage = 15): LengthAwarePaginator
    {
        return QrReceipt::where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find receipt by ID for customer.
     */
    public function findByIdForCustomer(int $id, int $customerId): ?QrReceipt
    {
        return QrReceipt::where('customer_id', $customerId)->find($id);
    }

    /**
     * Approve receipt.
     */
    public function approve(QrReceipt $receipt, int $pointsAwarded, ?int $approvedBy = null): bool
    {
        return $receipt->update([
            'status' => 'approved',
            'points_awarded' => $pointsAwarded,
            'approved_at' => now(),
            'approved_by' => $approvedBy,
        ]);
    }

    /**
     * Reject receipt.
     */
    public function reject(QrReceipt $receipt, ?int $approvedBy = null): bool
    {
        return $receipt->update([
            'status' => 'rejected',
            'approved_by' => $approvedBy,
        ]);
    }
}

