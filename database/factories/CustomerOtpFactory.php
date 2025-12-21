<?php

namespace Database\Factories;

use App\Core\Models\Customer;
use App\Core\Models\CustomerOtp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Core\Models\CustomerOtp>
 */
class CustomerOtpFactory extends Factory
{
    protected $model = CustomerOtp::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customer = Customer::factory()->create();

        return [
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'otp' => (string) fake()->numberBetween(100000, 999999),
            'type' => 'verification',
            'expires_at' => now()->addMinutes(5),
            'verified_at' => null,
        ];
    }

    /**
     * Indicate that the OTP is for password reset.
     */
    public function passwordReset(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'password_reset',
        ]);
    }

    /**
     * Indicate that the OTP is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the OTP is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinutes(10),
        ]);
    }
}
