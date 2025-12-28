<?php

namespace App\Core\Repositories;

use App\Core\Models\PromoCode;

class PromoCodeRepository
{
    /**
     * Find valid promo code by code.
     */
    public function findValidByCode(string $code): ?PromoCode
    {
        return PromoCode::where('code', strtoupper($code))
            ->valid()
            ->first();
    }

    /**
     * Find promo code by ID.
     */
    public function findById(int $id): ?PromoCode
    {
        return PromoCode::find($id);
    }

    /**
     * Check if promo code is valid for customer and order amount.
     */
    public function isValidForCustomer(PromoCode $promoCode, int $customerId, float $orderAmount): bool
    {
        if (!$promoCode->isValid()) {
            return false;
        }

        if ($orderAmount < $promoCode->minimum_order_amount) {
            return false;
        }

        // Check per-user limit
        if ($promoCode->usage_per_user_limit) {
            $userUsageCount = $promoCode->usages()
                ->where('customer_id', $customerId)
                ->count();

            if ($userUsageCount >= $promoCode->usage_per_user_limit) {
                return false;
            }
        }

        return true;
    }
}

