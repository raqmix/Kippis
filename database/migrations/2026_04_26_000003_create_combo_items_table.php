<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_product_id')
                ->constrained('products')
                ->cascadeOnDelete()
                ->comment('The parent combo product');
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete()
                ->comment('The included component product');
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_optional')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->index('combo_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_items');
    }
};
