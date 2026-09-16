<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Frozen list of {promo_code_id, code, name_json, type, amount_off,
        // description_json, free_items[]} computed during Cart::recalculate.
        // The resource serializer reads this directly so we don't re-run
        // the engine on every API hit; cart writes invalidate it via the
        // existing recalculate path.
        Schema::table('carts', function (Blueprint $table) {
            $table->json('applied_promotions_snapshot')->nullable()->after('points_discount');
        });

        // Same shape on orders so receipts / admin / Foodics history can
        // show "which promotions discounted this order" forever, even
        // after the source promo rows get archived or edited.
        Schema::table('orders', function (Blueprint $table) {
            $table->json('applied_promotions_snapshot')->nullable()->after('promo_discount');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('applied_promotions_snapshot');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('applied_promotions_snapshot');
        });
    }
};
