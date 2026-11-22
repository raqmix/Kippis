<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cup card (KIP-36, "every cup gets cheaper").
 *
 *   customers.cup_card_stamps          stamps on the current card
 *   customers.cup_card_last_stamp_at   last stamp; the card expires ttl_days after it
 *   carts/orders.cup_card_discount     EGP off the card cup (part of `discount`,
 *                                      NOT part of `app_discount`)
 *   cup_card_events                    audit + idempotency ledger
 *                                      (unique order_id + type)
 *
 * Additive and guarded so it runs on a fresh install and on prod's schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'cup_card_stamps')) {
                $table->unsignedSmallInteger('cup_card_stamps')->default(0);
            }
            if (! Schema::hasColumn('customers', 'cup_card_last_stamp_at')) {
                $table->timestamp('cup_card_last_stamp_at')->nullable();
            }
        });

        foreach (['carts', 'orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'cup_card_discount')) {
                    $table->decimal('cup_card_discount', 10, 2)->default(0);
                }
            });
        }

        if (! Schema::hasTable('cup_card_events')) {
            Schema::create('cup_card_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->string('type', 20); // stamp|unstamp|complete|expire|restore
                $table->unsignedSmallInteger('stamps_before')->default(0);
                $table->unsignedSmallInteger('stamps_after')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['order_id', 'type']);
                $table->index(['customer_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cup_card_events');

        foreach (['carts', 'orders'] as $tableName) {
            if (Schema::hasColumn($tableName, 'cup_card_discount')) {
                Schema::table($tableName, fn (Blueprint $t) => $t->dropColumn('cup_card_discount'));
            }
        }

        $cols = array_values(array_filter(
            ['cup_card_stamps', 'cup_card_last_stamp_at'],
            fn ($c) => Schema::hasColumn('customers', $c),
        ));
        if ($cols) {
            Schema::table('customers', fn (Blueprint $t) => $t->dropColumn($cols));
        }
    }
};
