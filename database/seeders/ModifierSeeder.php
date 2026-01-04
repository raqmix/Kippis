<?php

namespace Database\Seeders;

use App\Core\Models\Modifier;
use Illuminate\Database\Seeder;

class ModifierSeeder extends Seeder
{
    public function run(): void
    {
        // Base modifier types (3 فقط)
        $baseModifiers = [
            [
                'type' => 'size',
                'name_json' => [
                    'en' => 'Size',
                    'ar' => 'الحجم',
                ],
                'max_level' => null,
                'price' => 0.00,
                'is_active' => true,
            ],
            [
                'type' => 'smothing',
                'name_json' => [
                    'en' => 'Smothing',
                    'ar' => 'السلاسة',
                ],
                'max_level' => null,
                'price' => 0.00,
                'is_active' => true,
            ],
            [
                'type' => 'customize_modifires',
                'name_json' => [
                    'en' => 'Customize Modifires',
                    'ar' => 'تخصيص المعدلات',
                ],
                'max_level' => null,
                'price' => 0.00,
                'is_active' => true,
            ],
        ];

        // Size options (S/M/L)
        $sizeOptions = [
            [
                'type' => 'size',
                'name_json' => [
                    'en' => 'S',
                    'ar' => 'صغير',
                ],
                'max_level' => null,
                'price' => 0.00,
                'is_active' => true,
            ],
            [
                'type' => 'size',
                'name_json' => [
                    'en' => 'M',
                    'ar' => 'متوسط',
                ],
                'max_level' => null,
                'price' => 10.00,
                'is_active' => true,
            ],
            [
                'type' => 'size',
                'name_json' => [
                    'en' => 'L',
                    'ar' => 'كبير',
                ],
                'max_level' => null,
                'price' => 20.00,
                'is_active' => true,
            ],
        ];

        // Upsert base modifiers
        foreach ($baseModifiers as $data) {
            Modifier::query()->updateOrCreate(
                [
                    'type' => $data['type'],
                    // مطابقة على EN لتفادي مقارنة JSON كاملة
                    'name_json->en' => $data['name_json']['en'],
                ],
                $data
            );
        }

        // Upsert size options
        foreach ($sizeOptions as $data) {
            Modifier::query()->updateOrCreate(
                [
                    'type' => $data['type'],
                    'name_json->en' => $data['name_json']['en'],
                ],
                $data
            );
        }

        $this->command?->info('✅ Modifiers seeded successfully!');
    }
}
