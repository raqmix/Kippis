<?php

namespace Database\Seeders;

use App\Core\Models\Category;
use App\Core\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hotDrinksCategory = Category::where('name_json->en', 'Hot Drinks')->first();
        $coldDrinksCategory = Category::where('name_json->en', 'Cold Drinks')->first();
        $smoothiesCategory = Category::where('name_json->en', 'Smoothies')->first();
        $specialtyCategory = Category::where('name_json->en', 'Specialty Drinks')->first();
        $dessertsCategory = Category::where('name_json->en', 'Desserts')->first();

        $products = [];

        // Hot Drinks
        if ($hotDrinksCategory) {
            $products[] = [
                'category_id' => $hotDrinksCategory->id,
                'name_json' => [
                    'en' => 'Espresso',
                    'ar' => 'إسبريسو',
                ],
                'description_json' => [
                    'en' => 'Strong and bold espresso shot',
                    'ar' => 'جرعة إسبريسو قوية وجريئة',
                ],
                'base_price' => 12.00,
                'is_active' => true,
                'external_source' => 'local',
            ];
            $products[] = [
                'category_id' => $hotDrinksCategory->id,
                'name_json' => [
                    'en' => 'Cappuccino',
                    'ar' => 'كابتشينو',
                ],
                'description_json' => [
                    'en' => 'Espresso with steamed milk and foam',
                    'ar' => 'إسبريسو مع الحليب المبخر والرغوة',
                ],
                'base_price' => 18.00,
                'is_active' => true,
                'external_source' => 'local',
            ];
            $products[] = [
                'category_id' => $hotDrinksCategory->id,
                'name_json' => [
                    'en' => 'Latte',
                    'ar' => 'لاتيه',
                ],
                'description_json' => [
                    'en' => 'Smooth espresso with steamed milk',
                    'ar' => 'إسبريسو ناعم مع الحليب المبخر',
                ],
                'base_price' => 20.00,
                'is_active' => true,
                'external_source' => 'local',
            ];
        }

        // Cold Drinks
        if ($coldDrinksCategory) {
            $products[] = [
                'category_id' => $coldDrinksCategory->id,
                'name_json' => [
                    'en' => 'Iced Coffee',
                    'ar' => 'قهوة مثلجة',
                ],
                'description_json' => [
                    'en' => 'Chilled coffee served over ice',
                    'ar' => 'قهوة مبردة تقدم على الثلج',
                ],
                'base_price' => 16.00,
                'is_active' => true,
                'external_source' => 'local',
            ];
            $products[] = [
                'category_id' => $coldDrinksCategory->id,
                'name_json' => [
                    'en' => 'Cold Brew',
                    'ar' => 'قهوة باردة',
                ],
                'description_json' => [
                    'en' => 'Smooth cold-brewed coffee',
                    'ar' => 'قهوة باردة ناعمة',
                ],
                'base_price' => 22.00,
                'is_active' => true,
                'external_source' => 'local',
            ];
        }

        // Smoothies
        if ($smoothiesCategory) {
            $products[] = [
                'category_id' => $smoothiesCategory->id,
                'name_json' => [
                    'en' => 'Strawberry Smoothie',
                    'ar' => 'سموذي الفراولة',
                ],
                'description_json' => [
                    'en' => 'Fresh strawberry smoothie',
                    'ar' => 'سموذي الفراولة الطازجة',
                ],
                'base_price' => 25.00,
                'is_active' => true,
                'external_source' => 'local',
            ];
            $products[] = [
                'category_id' => $smoothiesCategory->id,
                'name_json' => [
                    'en' => 'Mango Smoothie',
                    'ar' => 'سموذي المانجو',
                ],
                'description_json' => [
                    'en' => 'Tropical mango smoothie',
                    'ar' => 'سموذي المانجو الاستوائي',
                ],
                'base_price' => 25.00,
                'is_active' => true,
                'external_source' => 'local',
            ];
        }

        // Specialty Drinks
        if ($specialtyCategory) {
            $products[] = [
                'category_id' => $specialtyCategory->id,
                'name_json' => [
                    'en' => 'Matcha Latte',
                    'ar' => 'لاتيه الماتشا',
                ],
                'description_json' => [
                    'en' => 'Green tea matcha with steamed milk',
                    'ar' => 'ماتشا الشاي الأخضر مع الحليب المبخر',
                ],
                'base_price' => 24.00,
                'is_active' => true,
                'external_source' => 'local',
            ];
        }

        // Desserts
        if ($dessertsCategory) {
            $products[] = [
                'category_id' => $dessertsCategory->id,
                'name_json' => [
                    'en' => 'Chocolate Cake',
                    'ar' => 'كعكة الشوكولاتة',
                ],
                'description_json' => [
                    'en' => 'Rich chocolate cake slice',
                    'ar' => 'شريحة كعكة شوكولاتة غنية',
                ],
                'base_price' => 30.00,
                'is_active' => true,
                'external_source' => 'local',
            ];
        }

        foreach ($products as $productData) {
            Product::firstOrCreate(
                [
                    'category_id' => $productData['category_id'],
                    'name_json' => $productData['name_json'],
                ],
                $productData
            );
        }

        $this->command->info('Products seeded successfully!');
    }
}

