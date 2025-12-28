<?php

namespace Database\Seeders;

use App\Core\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name_json' => [
                    'en' => 'Hot Drinks',
                    'ar' => 'المشروبات الساخنة',
                ],
                'description_json' => [
                    'en' => 'Warm and comforting hot beverages',
                    'ar' => 'مشروبات ساخنة ومريحة',
                ],
                'is_active' => true,
                'external_source' => 'local',
            ],
            [
                'name_json' => [
                    'en' => 'Cold Drinks',
                    'ar' => 'المشروبات الباردة',
                ],
                'description_json' => [
                    'en' => 'Refreshing cold beverages',
                    'ar' => 'مشروبات باردة منعشة',
                ],
                'is_active' => true,
                'external_source' => 'local',
            ],
            [
                'name_json' => [
                    'en' => 'Smoothies',
                    'ar' => 'السموذي',
                ],
                'description_json' => [
                    'en' => 'Fresh fruit smoothies',
                    'ar' => 'سموذي الفواكه الطازجة',
                ],
                'is_active' => true,
                'external_source' => 'local',
            ],
            [
                'name_json' => [
                    'en' => 'Specialty Drinks',
                    'ar' => 'المشروبات الخاصة',
                ],
                'description_json' => [
                    'en' => 'Unique and specialty beverages',
                    'ar' => 'مشروبات فريدة وخاصة',
                ],
                'is_active' => true,
                'external_source' => 'local',
            ],
            [
                'name_json' => [
                    'en' => 'Desserts',
                    'ar' => 'الحلويات',
                ],
                'description_json' => [
                    'en' => 'Sweet treats and desserts',
                    'ar' => 'الحلويات والوجبات الخفيفة',
                ],
                'is_active' => true,
                'external_source' => 'local',
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::firstOrCreate(
                ['name_json' => $categoryData['name_json']],
                $categoryData
            );
        }

        $this->command->info('Categories seeded successfully!');
    }
}

