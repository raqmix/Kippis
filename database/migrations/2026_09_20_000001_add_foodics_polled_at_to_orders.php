<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track when each order was last polled against Foodics.
 *
 * The status poll used to order by `updated_at`, which only moves when a
 * poll actually changes the status. An order whose status was unchanged —
 * or whose poll failed — kept the same `updated_at` and so stayed at the
 * front of the queue, getting re-polled every 5 minutes forever. With a
 * live 429 that meant the same stuck set burning the whole request budget
 * on every tick, indefinitely.
 *
 * Polling off this column instead means every poll moves the order to the
 * back of the line whether or not anything changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('foodics_polled_at')->nullable()->after('foodics_order_id');

            // The poll selects unfinished orders and takes the least
            // recently polled; this index serves that ordering directly.
            $table->index('foodics_polled_at', 'orders_foodics_polled_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_foodics_polled_at_index');
            $table->dropColumn('foodics_polled_at');
        });
    }
};
