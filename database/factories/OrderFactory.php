<?php

namespace Database\Factories;

use App\Core\Models\Order;
use App\Core\Models\Customer;
use App\Core\Models\Store;
use App\Core\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Core\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['received', 'mixing', 'ready', 'completed', 'cancelled'];
        $paymentMethods = ['cash', 'card', 'online'];
        $status = $this->faker->randomElement($statuses);
        
        $subtotal = $this->faker->randomFloat(2, 20, 500);
        $discount = $this->faker->randomFloat(2, 0, $subtotal * 0.3);
        $tax = $subtotal * 0.15; // 15% tax
        $total = $subtotal - $discount + $tax;

        // Generate items snapshot
        $itemsCount = $this->faker->numberBetween(1, 5);
        $items = [];
        for ($i = 0; $i < $itemsCount; $i++) {
            $items[] = [
                'product_id' => $this->faker->numberBetween(1, 50),
                'product_name' => $this->faker->words(3, true),
                'quantity' => $this->faker->numberBetween(1, 3),
                'price' => $this->faker->randomFloat(2, 10, 50),
                'modifiers' => [],
            ];
        }

        return [
            'store_id' => Store::factory(),
            'customer_id' => Customer::factory(),
            'status' => $status,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $total,
            'payment_method' => $this->faker->randomElement($paymentMethods),
            'pickup_code' => str_pad((string) $this->faker->numberBetween(0, 9999), 4, '0', STR_PAD_LEFT),
            'items_snapshot' => $items,
            'modifiers_snapshot' => [],
            'promo_code_id' => $this->faker->boolean(30) ? PromoCode::factory() : null,
            'promo_discount' => $discount,
            'created_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'updated_at' => function (array $attributes) {
                return $this->faker->dateTimeBetween($attributes['created_at'], 'now');
            },
        ];
    }

    /**
     * Indicate that the order is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the order is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $this->faker->randomElement(['received', 'mixing', 'ready']),
        ]);
    }

    /**
     * Indicate that the order is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}

