<?php

namespace App\Core\Repositories;

use App\Core\Models\PromoQrCode;

class PromoQrCodeRepository
{
    /**
     * Find QR code by code string.
     */
    public function findByCode(string $code): ?PromoQrCode
    {
        return PromoQrCode::where('code', $code)->first();
    }

    /**
     * Increment total usage count for a QR code.
     */
    public function incrementUsageCount(PromoQrCode $qrCode): bool
    {
        return $qrCode->increment('total_uses_count');
    }

    /**
     * Get usage count for a specific customer.
     */
    public function getUsageCountForCustomer(PromoQrCode $qrCode, int $customerId): int
    {
        return $qrCode->scans()
            ->where('customer_id', $customerId)
            ->count();
    }

    /**
     * Create a new QR code.
     */
    public function create(array $data): PromoQrCode
    {
        return PromoQrCode::create($data);
    }

    /**
     * Update QR code.
     */
    public function update(PromoQrCode $qrCode, array $data): bool
    {
        return $qrCode->update($data);
    }
}

