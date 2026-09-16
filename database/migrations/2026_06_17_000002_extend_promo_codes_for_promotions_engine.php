<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            // Translatable name / description so we can show a polished
            // "Park St — 30% off your first order" tile on the cart screen
            // instead of just the bare code.
            $table->json('name_json')->nullable()->after('code');
            $table->json('description_json')->nullable()->after('name_json');

            // Auto-apply means the engine evaluates the promo against the
            // current cart on every recalculate, with no code typed. The
            // existing `code` column becomes optional (null = auto only).
            $table->boolean('auto_apply')->default(false)->after('description_json');

            // When multiple auto-apply promos match, higher priority wins.
            // Ties broken by the larger discount.
            $table->integer('priority')->default(0)->after('auto_apply');

            // Auto-apply promos with stackable=true can combine with other
            // stackable ones; otherwise only the single best wins.
            $table->boolean('stackable')->default(false)->after('priority');

            // Type-specific config: percentage/fixed already use discount_value,
            // but new types need extra shape:
            //   buy_x_get_y: {buy: 10, get: 3}
            //   free_item:   {product_id: 42, quantity: 1}
            //   free_delivery: {} (no extra config)
            $table->json('config')->nullable()->after('stackable');

            // Generic gating conditions:
            //   {first_order_only: true}
            //   {segments: ['new_customer', 'loyal', 'staff']}
            //   {days_of_week: [1,2,3,4,5]}  // 1=Mon..7=Sun
            //   {time_window: {from: "06:00", to: "11:00"}}
            $table->json('conditions')->nullable()->after('config');
        });

        // Make `code` nullable so auto-apply promos can exist without one.
        // MySQL keeps the UNIQUE index but allows multiple NULL rows by
        // default, which is exactly what we want.
        DB::statement('ALTER TABLE promo_codes MODIFY code VARCHAR(255) NULL');

        // Widen discount_type from enum to a varchar so we can introduce
        // new types (buy_x_get_y, free_item, free_delivery) without
        // another enum-changing migration every time.
        DB::statement('ALTER TABLE promo_codes MODIFY discount_type VARCHAR(40) NOT NULL');

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->index(['auto_apply', 'active'], 'promo_codes_auto_apply_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropIndex('promo_codes_auto_apply_active_idx');
            $table->dropColumn([
                'name_json',
                'description_json',
                'auto_apply',
                'priority',
                'stackable',
                'config',
                'conditions',
            ]);
        });

        DB::statement('ALTER TABLE promo_codes MODIFY code VARCHAR(255) NOT NULL');
        DB::statement("ALTER TABLE promo_codes MODIFY discount_type ENUM('percentage', 'fixed') NOT NULL");
    }
};
