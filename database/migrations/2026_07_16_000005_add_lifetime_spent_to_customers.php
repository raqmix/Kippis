<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Cumulative lifetime spend in piasters (same unit as
            // orders.total * 100). Incremented in OrderObserver on the
            // ->completed transition; decremented in RefundService on
            // full refunds. Drives the SpendRewardService threshold
            // checker — see spend_reward_tiers.
            //
            // Backfilled below from historical completed orders so
            // existing customers get credit for prior spend the moment
            // this migration lands.
            $table->unsignedBigInteger('lifetime_spent_piasters')
                ->default(0)
                ->after('push_marketing_opt_in');
        });

        // Backfill from existing completed / voided-with-loyalty
        // orders. `voided` orders do NOT count (matches OrderObserver
        // which only awards on `completed`); we don't need to include
        // `mixing`/`ready` mid-flight either, since those never
        // completed. Refunded orders are still counted here — refund
        // reversal happens live via RefundService going forward; for
        // historical rows we accept the small overcount rather than
        // reconstruct every refund reversal.
        DB::statement("
            UPDATE customers c
            LEFT JOIN (
                SELECT customer_id, SUM(CAST(ROUND(total * 100) AS UNSIGNED)) AS spent
                FROM orders
                WHERE customer_id IS NOT NULL
                  AND status = 'completed'
                GROUP BY customer_id
            ) o ON o.customer_id = c.id
            SET c.lifetime_spent_piasters = COALESCE(o.spent, 0)
        ");
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('lifetime_spent_piasters');
        });
    }
};
