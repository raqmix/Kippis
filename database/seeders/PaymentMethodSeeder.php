<?php

namespace Database\Seeders;

use App\Core\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Seed the three static payment methods with stable IDs.
     */
    public function run(): void
    {
        $staticMethods = [
            ['id' => 1, 'name' => 'Cash',      'code' => 'cash',      'is_active' => true],
            ['id' => 2, 'name' => 'Card',      'code' => 'card',      'is_active' => true],
            ['id' => 3, 'name' => 'Apple Pay', 'code' => 'apple_pay', 'is_active' => true],
        ];

        foreach ($staticMethods as $method) {
            PaymentMethod::withTrashed()->updateOrCreate(
                ['id' => $method['id']],
                [
                    'name'       => $method['name'],
                    'code'       => $method['code'],
                    'is_active'  => $method['is_active'],
                    'channel_id' => null,
                    'deleted_at' => null,
                ]
            );
        }

        // Deactivate any legacy methods (wallet, google_pay, etc.)
        PaymentMethod::whereNotIn('code', ['cash', 'card', 'apple_pay'])
            ->update(['is_active' => false]);
    }
}
