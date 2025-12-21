<?php

namespace Database\Factories;

use App\Core\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Core\Models\Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'country_code' => '+' . fake()->numberBetween(1, 999),
            'birthdate' => fake()->date('Y-m-d', '-18 years'),
            'password' => Hash::make('password123'),
            'avatar' => null,
            'foodics_customer_id' => null,
            'is_verified' => false,
        ];
    }

    /**
     * Indicate that the customer is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
        ]);
    }
}
