<?php

namespace Database\Seeders;

use App\Core\Models\Category;
use App\Core\Models\Product;
use App\Core\Models\PromoCode;
use App\Core\Models\Store;
use Illuminate\Database\Seeder;

class PromoCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $promoCodes = [
            [
                'code' => 'WELCOME10',
                'discount_type' => 'percentage',
                'discount_value' => 10.00,
                'valid_from' => now(),
                'valid_to' => now()->addMonths(3),
                'usage_limit' => 1000,
                'usage_per_user_limit' => 1,
                'minimum_order_amount' => 50.00,
                'active' => true,
            ],
            [
                'code' => 'SAVE20',
                'discount_type' => 'percentage',
                'discount_value' => 20.00,
                'valid_from' => now(),
                'valid_to' => now()->addMonths(1),
                'usage_limit' => 500,
                'usage_per_user_limit' => 2,
                'minimum_order_amount' => 100.00,
                'active' => true,
            ],
            [
                'code' => 'FIXED50',
                'discount_type' => 'fixed',
                'discount_value' => 50.00,
                'valid_from' => now(),
                'valid_to' => now()->addWeeks(2),
                'usage_limit' => 200,
                'usage_per_user_limit' => 1,
                'minimum_order_amount' => 150.00,
                'active' => true,
            ],
            [
                'code' => 'SUMMER25',
                'discount_type' => 'percentage',
                'discount_value' => 25.00,
                'valid_from' => now(),
                'valid_to' => now()->addMonths(2),
                'usage_limit' => null,
                'usage_per_user_limit' => 3,
                'minimum_order_amount' => 75.00,
                'active' => true,
            ],
        ];

        foreach ($promoCodes as $promoData) {
            $promoCode = PromoCode::firstOrCreate(
                ['code' => $promoData['code']],
                $promoData
            );

            // Optionally attach to stores, categories, or products
            // For example, attach first promo to first store
            if ($promoCode->code === 'WELCOME10') {
                $store = Store::first();
                if ($store) {
                    $promoCode->stores()->syncWithoutDetaching([$store->id]);
                }
            }

            // Attach second promo to a category
            if ($promoCode->code === 'SAVE20') {
                $category = Category::first();
                if ($category) {
                    $promoCode->categories()->syncWithoutDetaching([$category->id]);
                }
            }

            // Attach third promo to a product
            if ($promoCode->code === 'FIXED50') {
                $product = Product::first();
                if ($product) {
                    $promoCode->products()->syncWithoutDetaching([$product->id]);
                }
            }
        }

        $this->command->info('Promo codes seeded successfully!');
    }
}

