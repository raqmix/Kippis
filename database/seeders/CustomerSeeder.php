<?php

namespace Database\Seeders;

use App\Core\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Common Saudi Arabia country codes
        $countryCodes = ['+966', '+966', '+966', '+1', '+44', '+971', '+20'];
        
        // Create verified customers
        $verifiedCustomers = [
            [
                'name' => 'Ahmed Al-Saud',
                'email' => 'ahmed.alsaud@example.com',
                'phone' => '501234567',
                'country_code' => '+966',
                'birthdate' => '1990-05-15',
                'password' => Hash::make('password123'),
                'is_verified' => true,
                'foodics_customer_id' => 'FOODICS_001',
            ],
            [
                'name' => 'Fatima Al-Rashid',
                'email' => 'fatima.alrashid@example.com',
                'phone' => '502345678',
                'country_code' => '+966',
                'birthdate' => '1992-08-20',
                'password' => Hash::make('password123'),
                'is_verified' => true,
                'foodics_customer_id' => 'FOODICS_002',
            ],
            [
                'name' => 'Mohammed Al-Otaibi',
                'email' => 'mohammed.alotaibi@example.com',
                'phone' => '503456789',
                'country_code' => '+966',
                'birthdate' => '1988-12-10',
                'password' => Hash::make('password123'),
                'is_verified' => true,
                'foodics_customer_id' => 'FOODICS_003',
            ],
            [
                'name' => 'Noura Al-Mansouri',
                'email' => 'noura.almansouri@example.com',
                'phone' => '504567890',
                'country_code' => '+966',
                'birthdate' => '1995-03-25',
                'password' => Hash::make('password123'),
                'is_verified' => true,
                'foodics_customer_id' => null,
            ],
            [
                'name' => 'Khalid Al-Zahrani',
                'email' => 'khalid.alzahrani@example.com',
                'phone' => '505678901',
                'country_code' => '+966',
                'birthdate' => '1991-07-18',
                'password' => Hash::make('password123'),
                'is_verified' => true,
                'foodics_customer_id' => 'FOODICS_004',
            ],
        ];

        foreach ($verifiedCustomers as $customerData) {
            Customer::create($customerData);
        }

        // Create unverified customers
        $unverifiedCustomers = [
            [
                'name' => 'Sara Al-Harbi',
                'email' => 'sara.alharbi@example.com',
                'phone' => '506789012',
                'country_code' => '+966',
                'birthdate' => '1993-09-12',
                'password' => Hash::make('password123'),
                'is_verified' => false,
                'foodics_customer_id' => null,
            ],
            [
                'name' => 'Omar Al-Shammari',
                'email' => 'omar.alshammari@example.com',
                'phone' => '507890123',
                'country_code' => '+966',
                'birthdate' => '1989-11-30',
                'password' => Hash::make('password123'),
                'is_verified' => false,
                'foodics_customer_id' => null,
            ],
            [
                'name' => 'Layla Al-Ghamdi',
                'email' => 'layla.alghamdi@example.com',
                'phone' => '508901234',
                'country_code' => '+966',
                'birthdate' => '1994-04-05',
                'password' => Hash::make('password123'),
                'is_verified' => false,
                'foodics_customer_id' => null,
            ],
        ];

        foreach ($unverifiedCustomers as $customerData) {
            Customer::create($customerData);
        }

        // Create additional random customers using factory
        Customer::factory()
            ->count(20)
            ->create();

        // Create some verified random customers
        Customer::factory()
            ->count(10)
            ->verified()
            ->create();

        // Create some customers with Foodics IDs
        Customer::factory()
            ->count(5)
            ->state(function (array $attributes) {
                return [
                    'foodics_customer_id' => 'FOODICS_' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT),
                    'is_verified' => true,
                ];
            })
            ->create();
    }
}
