<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-branch availability pivot. Matches the existing product_store
     * convention: zero rows for an item == available at all branches;
     * one or more rows == scoped to those branches only. Admin can run
     * a "Park-St exclusive" reward without per-branch row maintenance.
     */
    public function up(): void
    {
        Schema::create('redeem_item_store', function (Blueprint $table) {
            $table->id();
            $table->foreignId('redeem_item_id')->constrained('redeem_items')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['redeem_item_id', 'store_id']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redeem_item_store');
    }
};
