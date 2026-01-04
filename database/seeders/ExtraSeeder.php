<?php

namespace Database\Seeders;

use App\Core\Models\Modifier;
use Illuminate\Database\Seeder;

class ExtraSeeder extends Seeder
{
    public function run(): void
    {
        // Extra modifier options
        $extraOptions = [
            [
                'type' => 'extra',
                'name_json' => [
                    'en' => 'Extra Shot',
                    'ar' => 'جرعة إضافية',
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
                'price' => 3.00,
                'is_active' => true,
            ],
            [
                'type' => 'extra',
                'name_json' => [
                    'en' => 'Chocolate Syrup',
                    'ar' => 'شراب الشوكولاتة',
                ],
                'max_level' => null,
                'price' => 2.50,
                'is_active' => true,
            ],
            [
                'type' => 'extra',
                'name_json' => [
                    'en' => 'Caramel Drizzle',
                    'ar' => 'قطرات الكراميل',
                ],
                'max_level' => null,
                'price' => 2.50,
                'is_active' => true,
            ],
            [
                'type' => 'extra',
                'name_json' => [
                    'en' => 'Vanilla Extract',
                    'ar' => 'مستخلص الفانيليا',
                ],
                'max_level' => null,
                'price' => 1.50,
                'is_active' => true,
            ],
        ];

        // Upsert extra options
        foreach ($extraOptions as $data) {
            Modifier::query()->updateOrCreate(
                [
                    'type' => $data['type'],
                    'name_json->en' => $data['name_json']['en'],
                ],
                $data
            );
        }

        $this->command?->info('✅ Extra modifiers seeded successfully!');
    }
}

