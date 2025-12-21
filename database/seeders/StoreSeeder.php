<?php

namespace Database\Seeders;

use App\Core\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stores = [
            [
                'name' => 'Downtown Store',
                'name_localized' => [
                    'en' => 'Downtown Store',
                    'ar' => 'متجر وسط المدينة',
                ],
                'address' => 'King Fahd Road, Al Olaya, Riyadh 12211',
                'latitude' => 24.7136,
                'longitude' => 46.6753,
                'open_time' => '09:00',
                'close_time' => '22:00',
                'is_active' => true,
                'receive_online_orders' => true,
                'foodics_branch_id' => 'BRANCH_001',
                'synced_from_foodics_at' => now()->subDays(5),
            ],
            [
                'name' => 'Mall Branch',
                'name_localized' => [
                    'en' => 'Mall Branch',
                    'ar' => 'فرع المول',
                ],
                'address' => 'Riyadh Park Mall, King Fahd Road, Riyadh',
                'latitude' => 24.7200,
                'longitude' => 46.6800,
                'open_time' => '10:00',
                'close_time' => '23:00',
                'is_active' => true,
                'receive_online_orders' => true,
                'foodics_branch_id' => 'BRANCH_002',
                'synced_from_foodics_at' => now()->subDays(3),
            ],
            [
                'name' => 'North Branch',
                'name_localized' => [
                    'en' => 'North Branch',
                    'ar' => 'الفرع الشمالي',
                ],
                'address' => 'Prince Turki bin Abdulaziz Al Awwal Road, Al Malqa, Riyadh',
                'latitude' => 24.7500,
                'longitude' => 46.7000,
                'open_time' => '08:00',
                'close_time' => '21:00',
                'is_active' => true,
                'receive_online_orders' => true,
                'foodics_branch_id' => null,
                'synced_from_foodics_at' => null,
            ],
            [
                'name' => 'Airport Store',
                'name_localized' => [
                    'en' => 'Airport Store',
                    'ar' => 'متجر المطار',
                ],
                'address' => 'King Khalid International Airport, Riyadh',
                'latitude' => 24.9584,
                'longitude' => 46.6989,
                'open_time' => '06:00',
                'close_time' => '02:00',
                'is_active' => true,
                'receive_online_orders' => true,
                'foodics_branch_id' => 'BRANCH_003',
                'synced_from_foodics_at' => now()->subDays(1),
            ],
            [
                'name' => 'South Branch',
                'name_localized' => [
                    'en' => 'South Branch',
                    'ar' => 'الفرع الجنوبي',
                ],
                'address' => 'King Abdulaziz Road, Al Wurud, Riyadh',
                'latitude' => 24.6500,
                'longitude' => 46.6500,
                'open_time' => '09:30',
                'close_time' => '22:30',
                'is_active' => true,
                'receive_online_orders' => true,
                'foodics_branch_id' => 'BRANCH_004',
                'synced_from_foodics_at' => now()->subDays(7),
            ],
            [
                'name' => 'West Branch',
                'name_localized' => [
                    'en' => 'West Branch',
                    'ar' => 'الفرع الغربي',
                ],
                'address' => 'King Fahd Road, Al Nakheel, Riyadh',
                'latitude' => 24.7000,
                'longitude' => 46.6200,
                'open_time' => '10:00',
                'close_time' => '23:00',
                'is_active' => true,
                'receive_online_orders' => false,
                'foodics_branch_id' => null,
                'synced_from_foodics_at' => null,
            ],
            [
                'name' => 'East Branch',
                'name_localized' => [
                    'en' => 'East Branch',
                    'ar' => 'الفرع الشرقي',
                ],
                'address' => 'King Saud Road, Al Malaz, Riyadh',
                'latitude' => 24.6800,
                'longitude' => 46.7200,
                'open_time' => '08:00',
                'close_time' => '20:00',
                'is_active' => false,
                'receive_online_orders' => true,
                'foodics_branch_id' => null,
                'synced_from_foodics_at' => null,
            ],
            [
                'name' => 'University Store',
                'name_localized' => [
                    'en' => 'University Store',
                    'ar' => 'متجر الجامعة',
                ],
                'address' => 'King Saud University, Riyadh',
                'latitude' => 24.7225,
                'longitude' => 46.6256,
                'open_time' => '07:00',
                'close_time' => '23:00',
                'is_active' => true,
                'receive_online_orders' => true,
                'foodics_branch_id' => 'BRANCH_005',
                'synced_from_foodics_at' => now()->subHours(12),
            ],
        ];

        foreach ($stores as $storeData) {
            Store::firstOrCreate(
                ['name' => $storeData['name']],
                $storeData
            );
        }

        $this->command->info('Stores seeded successfully!');
    }
}

