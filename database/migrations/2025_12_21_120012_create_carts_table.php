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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('cascade');
            $table->string('session_id')->nullable(); // For guest carts
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->onDelete('set null');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('customer_id');
            $table->index('session_id');
            $table->index('store_id');
            $table->index(['customer_id', 'store_id']);
            $table->index(['session_id', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};

