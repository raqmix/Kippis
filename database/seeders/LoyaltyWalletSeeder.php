<?php

namespace Database\Seeders;

use App\Core\Models\Customer;
use App\Core\Models\LoyaltyWallet;
use Illuminate\Database\Seeder;

class LoyaltyWalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all customers
        $customers = Customer::all();

        foreach ($customers as $customer) {
            // Create wallet if it doesn't exist
            $wallet = LoyaltyWallet::firstOrCreate(
                ['customer_id' => $customer->id],
                ['points' => 0]
            );

            // Add some initial points to verified customers
            if ($customer->is_verified && $wallet->points == 0) {
                $initialPoints = rand(100, 500);
                $wallet->addPoints(
                    $initialPoints,
                    'earned',
                    'Welcome bonus points',
                    'system',
                    null
                );
            }
        }

        $this->command->info('Loyalty wallets seeded successfully!');
    }
}

