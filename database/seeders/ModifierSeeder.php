<?php

namespace Database\Seeders;

use App\Core\Models\Modifier;
use Illuminate\Database\Seeder;

class ModifierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modifiers = [
            // Sweetness levels
            [
                'type' => 'sweetness',
                'name_json' => [
                    'en' => 'No Sugar',
                    'ar' => 'بدون سكر',
                ],
                'max_level' => 5,
                'price' => 0.00,
                'is_active' => true,
            ],
            [
                'type' => 'sweetness',
                'name_json' => [
                    'en' => 'Light Sweet',
                    'ar' => 'حلو خفيف',
                ],
                'max_level' => 5,
                'price' => 0.00,
                'is_active' => true,
            ],
            [
                'type' => 'sweetness',
                'name_json' => [
                    'en' => 'Medium Sweet',
                    'ar' => 'حلو متوسط',
                ],
                'max_level' => 5,
                'price' => 0.00,
                'is_active' => true,
            ],
            [
                'type' => 'sweetness',
                'name_json' => [
                    'en' => 'Sweet',
                    'ar' => 'حلو',
                ],
                'max_level' => 5,
                'price' => 0.00,
                'is_active' => true,
            ],
            [
                'type' => 'sweetness',
                'name_json' => [
                    'en' => 'Very Sweet',
                    'ar' => 'حلو جداً',
                ],
                'max_level' => 5,
                'price' => 0.00,
                'is_active' => true,
            ],

            // Fizz levels
            [
                'type' => 'fizz',
                'name_json' => [
                    'en' => 'No Fizz',
                    'ar' => 'بدون فقاعات',
                ],
                'max_level' => 3,
                'price' => 0.00,
                'is_active' => true,
            ],
            [
                'type' => 'fizz',
                'name_json' => [
                    'en' => 'Light Fizz',
                    'ar' => 'فقاعات خفيفة',
                ],
                'max_level' => 3,
                'price' => 2.00,
                'is_active' => true,
            ],
            [
                'type' => 'fizz',
                'name_json' => [
                    'en' => 'Extra Fizz',
                    'ar' => 'فقاعات إضافية',
                ],
                'max_level' => 3,
                'price' => 4.00,
                'is_active' => true,
            ],

            // Caffeine levels
            [
                'type' => 'caffeine',
                'name_json' => [
                    'en' => 'Decaf',
                    'ar' => 'منزوع الكافيين',
                ],
                'max_level' => 3,
                'price' => 0.00,
                'is_active' => true,
            ],
            [
                'type' => 'caffeine',
                'name_json' => [
                    'en' => 'Regular',
                    'ar' => 'عادي',
                ],
                'max_level' => 3,
                'price' => 0.00,
                'is_active' => true,
            ],
            [
                'type' => 'caffeine',
                'name_json' => [
                    'en' => 'Extra Shot',
                    'ar' => 'جرعة إضافية',
                ],
                'max_level' => 3,
                'price' => 5.00,
                'is_active' => true,
            ],

            // Extras
            [
                'type' => 'extra',
                'name_json' => [
                    'en' => 'Boba Pearls',
                    'ar' => 'لآلئ البوبا',
                ],
                'max_level' => null,
                'price' => 8.00,
                'is_active' => true,
            ],
            [
                'type' => 'extra',
                'name_json' => [
                    'en' => 'Honey Foam',
                    'ar' => 'رغوة العسل',
                ],
                'max_level' => null,
                'price' => 6.00,
                'is_active' => true,
            ],
            [
                'type' => 'extra',
                'name_json' => [
                    'en' => 'Chocolate Drizzle',
                    'ar' => 'رش الشوكولاتة',
                ],
                'max_level' => null,
                'price' => 5.00,
                'is_active' => true,
            ],
            [
                'type' => 'extra',
                'name_json' => [
                    'en' => 'Whipped Cream',
                    'ar' => 'كريمة مخفوقة',
                ],
                'max_level' => null,
                'price' => 4.00,
                'is_active' => true,
            ],
            [
                'type' => 'extra',
                'name_json' => [
                    'en' => 'Caramel Syrup',
                    'ar' => 'شراب الكراميل',
                ],
                'max_level' => null,
                'price' => 3.00,
                'is_active' => true,
            ],
        ];

        foreach ($modifiers as $modifierData) {
            Modifier::firstOrCreate(
                [
                    'type' => $modifierData['type'],
                    'name_json' => $modifierData['name_json'],
                ],
                $modifierData
            );
        }

        $this->command->info('Modifiers seeded successfully!');
    }
}

