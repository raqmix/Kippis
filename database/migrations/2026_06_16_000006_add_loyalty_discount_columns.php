<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cart + Order columns for loyalty-as-discount.
 *
 * Two new ways to discount a cart:
 *   wallet_item_id  — customer picked a claimed reward from their wallet;
 *                     the wallet item's linked product subsidises a line
 *                     in the cart (wallet_discount = product.base_price).
 *   points_used     — customer spent raw points off their loyalty balance
 *                     for an arbitrary EGP-off using
 *                     `loyalty.points_to_egp_rate` setting (points_discount).
 *
 * Both stack with the existing promo path. Mirrored to `orders` so refunds
 * can re-credit the right thing without re-deriving it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('wallet_item_id')->nullable()
                ->after('promo_code_id')
                ->constrained('customer_redeem_wallet')->nullOnDelete();
            $table->decimal('wallet_discount', 10, 2)->default(0)->after('wallet_item_id');
            $table->unsignedInteger('points_used')->default(0)->after('wallet_discount');
            $table->decimal('points_discount', 10, 2)->default(0)->after('points_used');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('wallet_item_id')->nullable()
                ->after('promo_code_id')
                ->constrained('customer_redeem_wallet')->nullOnDelete();
            $table->decimal('wallet_discount', 10, 2)->default(0)->after('wallet_item_id');
            $table->unsignedInteger('points_used')->default(0)->after('wallet_discount');
            $table->decimal('points_discount', 10, 2)->default(0)->after('points_used');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['wallet_item_id']);
            $table->dropColumn(['wallet_item_id', 'wallet_discount', 'points_used', 'points_discount']);
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['wallet_item_id']);
            $table->dropColumn(['wallet_item_id', 'wallet_discount', 'points_used', 'points_discount']);
        });
    }
};
