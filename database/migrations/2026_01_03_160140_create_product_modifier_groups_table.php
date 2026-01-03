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
        Schema::create('product_modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('modifier_id')->constrained('modifiers')->onDelete('cascade');
            $table->boolean('is_required')->default(false);
            $table->integer('min_select')->nullable(); // Minimum level to select
            $table->integer('max_select')->nullable(); // Maximum level to select
            $table->timestamps();

            // Ensure unique product-modifier combination
            $table->unique(['product_id', 'modifier_id']);
            
            // Indexes
            $table->index('product_id');
            $table->index('modifier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_modifier_groups');
    }
};
