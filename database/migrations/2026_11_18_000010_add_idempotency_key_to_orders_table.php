<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kiosk checkout idempotency. The kiosk sends an Idempotency-Key header
     * (a UUID) per checkout attempt; a retry after a timeout carries the same
     * key and gets the original order back instead of a duplicate.
     *
     * Additive only: a nullable column (existing rows stay NULL) and a unique
     * index. NULLs never collide in a MySQL/MariaDB unique index, so orders
     * without a key are unaffected. The unique index is what stops two
     * concurrent retries from both creating an order.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 100 chars keeps the composite index well under InnoDB's
            // 767-byte prefix limit even on utf8mb4 / COMPACT row format.
            $table->string('idempotency_key', 100)->nullable()->after('pickup_code');
            $table->unique(['store_id', 'idempotency_key'], 'orders_store_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_store_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
