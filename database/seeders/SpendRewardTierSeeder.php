<?php

namespace Database\Seeders;

use App\Core\Models\SpendRewardTier;
use App\Support\Money;
use Illuminate\Database\Seeder;

/**
 * Seeds the client's launch spend-milestone tier:
 * "Spend EGP 2000 → free (salad OR sandwich) + drink."
 *
 * category_ids are LEFT EMPTY on purpose — ops must open the tier in
 * Filament and pick which category IDs count as salad/sandwich vs drink
 * (they depend on the live catalog and we don't want to guess). Until
 * the categories are set, the tier issues vouchers on threshold
 * crossing but the choice picker on the customer side has zero
 * options to pick from. This is intentional — surfaces the "please
 * configure me" state instead of silently drawing from the wrong
 * categories.
 *
 * Uses updateOrCreate keyed on name_en so re-running the seed doesn't
 * duplicate the row.
 */
class SpendRewardTierSeeder extends Seeder
{
    public function run(): void
    {
        SpendRewardTier::updateOrCreate(
            ['name_en' => 'Free meal on us — spend EGP 2,000'],
            [
                'name_ar' => 'وجبة مجانية — أنفق ٢٠٠٠ ج.م',
                'description_en' => 'Spend EGP 2,000 across your orders and pick a free salad or sandwich, plus a free drink.',
                'description_ar' => 'أنفق ٢٠٠٠ جنيه على طلباتك واختر سلطة أو ساندويتش مجانًا مع مشروب.',
                'threshold_piasters' => Money::toPiasters(2000),
                'cycle' => 'once',
                'choice_groups' => [
                    [
                        'label_en' => 'Main',
                        'label_ar' => 'الوجبة الرئيسية',
                        // Ops picks the salad + sandwich category IDs
                        // from Filament. Empty here = "no eligible
                        // options yet" — voucher issues but customer
                        // can't pick until this is filled.
                        'category_ids' => [],
                        'quantity' => 1,
                    ],
                    [
                        'label_en' => 'Drink',
                        'label_ar' => 'المشروب',
                        'category_ids' => [],
                        'quantity' => 1,
                    ],
                ],
                'voucher_ttl_days' => 60,
                'active' => true,
                'sort_order' => 100,
            ]
        );

        $this->command->info('Seeded 1 SpendRewardTier — ops must set choice-group category_ids in Filament.');
    }
}
