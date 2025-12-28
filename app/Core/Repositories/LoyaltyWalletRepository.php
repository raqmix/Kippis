<?php

namespace App\Core\Repositories;

use App\Core\Models\LoyaltyWallet;

class LoyaltyWalletRepository
{
    /**
     * Get or create wallet for customer.
     */
    public function getOrCreateForCustomer(int $customerId): LoyaltyWallet
    {
        return LoyaltyWallet::firstOrCreate(
            ['customer_id' => $customerId],
            ['points' => 0]
        );
    }

    /**
     * Find wallet by customer ID.
     */
    public function findByCustomerId(int $customerId): ?LoyaltyWallet
    {
        return LoyaltyWallet::where('customer_id', $customerId)->first();
    }

    /**
     * Add points to wallet.
     */
    public function addPoints(
        LoyaltyWallet $wallet,
        int $points,
        string $type = 'earned',
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $createdBy = null
    ) {
        return $wallet->addPoints($points, $type, $description, $referenceType, $referenceId, $createdBy);
    }

    /**
     * Deduct points from wallet.
     */
    public function deductPoints(
        LoyaltyWallet $wallet,
        int $points,
        string $type = 'redeemed',
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $createdBy = null
    ) {
        return $wallet->deductPoints($points, $type, $description, $referenceType, $referenceId, $createdBy);
    }
}

