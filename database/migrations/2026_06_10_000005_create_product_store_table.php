<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-branch product availability. A row links a product to one store —
 * customer-facing catalog endpoints (kiosk + mobile app) filter by the
 * authenticated/selected store using this pivot. A product with NO rows
 * here is treated as "available everywhere" so legacy globally-synced
 * products keep working without a backfill. Per-branch syncs populate
 * this idempotently; admin can also flip availability per row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_store', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'store_id']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_store');
    }
};
