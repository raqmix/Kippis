<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App-only discount (app brief items 1, 2).
 *
 *   platform            mobile|web|kiosk|squad|counter|unknown (null = legacy row)
 *   app_discount        EGP taken off by the app offer (part of `discount`)
 *   promo_discount      carts only: the promotion-engine share of `discount`
 *                       (orders already have the column)
 *   discount_source     app_offer|promotion|null: which of the two
 *                       non-combinable discounts won
 *   app_offer_snapshot  {eligible, percent, app_discount, promo_discount,
 *                       winner, suppressed_promotions[]} so the app can
 *                       explain "your app discount beats this code"
 *
 * Additive and guarded so it runs on a fresh install and on prod's schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['carts', 'orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'platform')) {
                    $table->string('platform', 20)->nullable()->index();
                }
                if (! Schema::hasColumn($tableName, 'app_discount')) {
                    $table->decimal('app_discount', 10, 2)->default(0);
                }
                if ($tableName === 'carts' && ! Schema::hasColumn($tableName, 'promo_discount')) {
                    $table->decimal('promo_discount', 10, 2)->default(0);
                }
                if (! Schema::hasColumn($tableName, 'discount_source')) {
                    $table->string('discount_source', 20)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'app_offer_snapshot')) {
                    $table->json('app_offer_snapshot')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['carts', 'orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'platform')) {
                    $table->dropIndex([ 'platform' ]);
                }
                $cols = array_values(array_filter(
                    ['platform', 'app_discount', 'discount_source', 'app_offer_snapshot', $tableName === 'carts' ? 'promo_discount' : null],
                    fn ($c) => $c && Schema::hasColumn($tableName, $c),
                ));
                if ($cols) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
