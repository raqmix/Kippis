<?php

namespace Database\Seeders;

use App\Core\Models\Order;
use App\Core\Models\Customer;
use App\Core\Models\Store;
use App\Core\Models\Product;
use App\Core\Models\PromoCode;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::all();
        $stores = Store::all();
        $products = Product::where('is_active', true)->get();
        $promoCodes = PromoCode::where('active', true)->get();

        if ($customers->isEmpty() || $stores->isEmpty() || $products->isEmpty()) {
            $this->command->warn('Skipping OrderSeeder: Required data (customers, stores, products) not found. Please run CustomerSeeder, StoreSeeder, and ProductSeeder first.');
            return;
        }

        $statuses = ['received', 'mixing', 'ready', 'completed', 'cancelled'];
        $paymentMethods = ['cash', 'card', 'online'];

        // Create completed orders (past orders)
        $this->command->info('Creating completed orders...');
        for ($i = 0; $i < 50; $i++) {
            $this->createOrder([
                'status' => 'completed',
                'customers' => $customers,
                'stores' => $stores,
                'products' => $products,
                'promoCodes' => $promoCodes,
                'paymentMethods' => $paymentMethods,
                'daysAgo' => rand(1, 90),
            ]);
        }

        // Create cancelled orders
        $this->command->info('Creating cancelled orders...');
        for ($i = 0; $i < 10; $i++) {
            $this->createOrder([
                'status' => 'cancelled',
                'customers' => $customers,
                'stores' => $stores,
                'products' => $products,
                'promoCodes' => $promoCodes,
                'paymentMethods' => $paymentMethods,
                'daysAgo' => rand(1, 30),
            ]);
        }

        // Create active orders (received, mixing, ready)
        $this->command->info('Creating active orders...');
        $activeStatuses = ['received', 'mixing', 'ready'];
        for ($i = 0; $i < 20; $i++) {
            $this->createOrder([
                'status' => $activeStatuses[array_rand($activeStatuses)],
                'customers' => $customers,
                'stores' => $stores,
                'products' => $products,
                'promoCodes' => $promoCodes,
                'paymentMethods' => $paymentMethods,
                'daysAgo' => rand(0, 2),
            ]);
        }

        $this->command->info('Created ' . Order::count() . ' orders.');
    }

    /**
     * Create a single order with items.
     */
    protected function createOrder(array $data): void
    {
        $customer = $data['customers']->random();
        $store = $data['stores']->random();
        $status = $data['status'];
        $paymentMethod = $data['paymentMethods'][array_rand($data['paymentMethods'])];
        $daysAgo = $data['daysAgo'] ?? 0;

        // Select 1-5 products
        $itemsCount = rand(1, 5);
        $selectedProducts = $data['products']->random(min($itemsCount, $data['products']->count()));

        $items = [];
        $subtotal = 0;

        foreach ($selectedProducts as $product) {
            $quantity = rand(1, 3);
            $price = $product->base_price;
            $itemTotal = $price * $quantity;
            $subtotal += $itemTotal;

            $items[] = [
                'product_id' => $product->id,
                'product_name' => $product->getName('en'),
                'quantity' => $quantity,
                'price' => $price,
                'modifiers' => [],
            ];
        }

        // Apply promo code randomly (20% chance)
        $promoCode = null;
        $promoDiscount = 0;
        if ($data['promoCodes']->isNotEmpty() && rand(1, 100) <= 20) {
            $promoCode = $data['promoCodes']->random();
            if ($subtotal >= $promoCode->minimum_order_amount) {
                if ($promoCode->discount_type === 'percentage') {
                    $promoDiscount = ($subtotal * $promoCode->discount_value) / 100;
                } else {
                    $promoDiscount = min($promoCode->discount_value, $subtotal);
                }
            }
        }

        $tax = $subtotal * 0.15; // 15% tax
        $discount = $promoDiscount;
        $total = $subtotal - $discount + $tax;

        $createdAt = now()->subDays($daysAgo)->subHours(rand(0, 23))->subMinutes(rand(0, 59));

        Order::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'status' => $status,
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2),
            'payment_method' => $paymentMethod,
            'pickup_code' => str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT),
            'items_snapshot' => $items,
            'modifiers_snapshot' => [],
            'promo_code_id' => $promoCode?->id,
            'promo_discount' => round($promoDiscount, 2),
            'created_at' => $createdAt,
            'updated_at' => $createdAt->copy()->addMinutes(rand(5, 120)),
        ]);
    }
}

