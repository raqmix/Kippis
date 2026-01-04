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
        // Base modifier types
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

        // Size option modifiers (S, M, L)
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

        // Create base modifiers
        foreach ($baseModifiers as $modifierData) {
            Modifier::updateOrCreate(
                [
                    'type' => $modifierData['type'],
                    'name_json' => $modifierData['name_json'],
                ],
                $modifierData
            );
        }

        // Create size option modifiers
        foreach ($sizeOptions as $modifierData) {
            Modifier::updateOrCreate(
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

