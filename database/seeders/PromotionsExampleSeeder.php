<?php

namespace Database\Seeders;

use App\Core\Models\PromoCode;
use App\Core\Models\Store;
use Illuminate\Database\Seeder;

/**
 * Seeds the two reference promotions that exercise the engine end-to-end:
 *   - Factory branch: "Buy 10 meals, get 3 free" (buy_x_get_y, auto-apply)
 *   - Park St branch: "30% off your first app order" (percentage, first-order-only, auto-apply)
 *
 * Idempotent — re-runs upsert on a stable name slug so seeding a fresh
 * env or refreshing existing rows both work without dupes.
 */
class PromotionsExampleSeeder extends Seeder
{
    public function run(): void
    {
        $factory = Store::where('name', 'LIKE', '%Factory%')->first();
        $parkSt = Store::where('name', 'LIKE', '%Park%')->first();

        $this->seedFactory($factory);
        $this->seedParkSt($parkSt);
    }

    private function seedFactory(?Store $factory): void
    {
        if (!$factory) {
            $this->command?->warn('Factory branch not found — skipping Factory buy-10-get-3 seed.');
            return;
        }

        $promo = PromoCode::updateOrCreate(
            ['code' => 'FACTORY_10_GET_3'],
            [
                'name_json' => [
                    'en' => 'Factory — Buy 10, get 3 free',
                    'ar' => 'فاكتوري — اشترِ ١٠ واحصل على ٣ مجاناً',
                ],
                'description_json' => [
                    'en' => 'Every 10 meals at Factory branch unlock 3 free meals — automatically applied.',
                    'ar' => 'كل ١٠ وجبات من فرع فاكتوري تحصل على ٣ وجبات مجانية تلقائياً.',
                ],
                'discount_type' => PromoCode::TYPE_BUY_X_GET_Y,
                'discount_value' => 0,
                'auto_apply' => true,
                'priority' => 10,
                'stackable' => false,
                'config' => ['buy' => 10, 'get' => 3],
                'conditions' => null,
                'valid_from' => now()->subDay(),
                'valid_to' => now()->addYears(2),
                'usage_limit' => null,
                'usage_per_user_limit' => null,
                'minimum_order_amount' => 0,
                'active' => true,
            ],
        );

        $promo->stores()->sync([$factory->id]);

        $this->command?->info("Seeded Factory buy-10-get-3 promo (id={$promo->id}).");
    }

    private function seedParkSt(?Store $parkSt): void
    {
        if (!$parkSt) {
            $this->command?->warn('Park St branch not found — skipping Park St 30%-first-order seed.');
            return;
        }

        $promo = PromoCode::updateOrCreate(
            ['code' => 'PARKST_FIRST30'],
            [
                'name_json' => [
                    'en' => 'Park St — 30% off your first order',
                    'ar' => 'بارك ستريت — خصم ٣٠٪ على أول طلب',
                ],
                'description_json' => [
                    'en' => 'New to Kippis at Park St? Take 30% off your first order automatically.',
                    'ar' => 'جديد على كيبيس في بارك ستريت؟ احصل على خصم ٣٠٪ تلقائياً على أول طلب.',
                ],
                'discount_type' => PromoCode::TYPE_PERCENTAGE,
                'discount_value' => 30,
                'auto_apply' => true,
                'priority' => 20,
                'stackable' => false,
                'config' => null,
                'conditions' => [
                    'first_order_only' => true,
                    'segments' => [PromoCode::SEGMENT_NEW_CUSTOMER],
                ],
                'valid_from' => now()->subDay(),
                'valid_to' => now()->addYears(2),
                'usage_limit' => null,
                'usage_per_user_limit' => 1,
                'minimum_order_amount' => 0,
                'active' => true,
            ],
        );

        $promo->stores()->sync([$parkSt->id]);

        $this->command?->info("Seeded Park St 30%-first-order promo (id={$promo->id}).");
    }
}
