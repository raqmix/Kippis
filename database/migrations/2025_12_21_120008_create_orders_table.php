<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->enum('status', ['received', 'mixing', 'ready', 'completed', 'cancelled'])->default('received');
            $table->decimal('total', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->string('payment_method');
            $table->string('pickup_code')->unique();
            $table->json('items_snapshot'); // Snapshot of order items
            $table->json('modifiers_snapshot')->nullable(); // Snapshot of modifiers applied
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->onDelete('set null');
            $table->decimal('promo_discount', 10, 2)->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('payment_method');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

