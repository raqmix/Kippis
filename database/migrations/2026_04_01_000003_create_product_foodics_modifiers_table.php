<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot: which Foodics modifiers are attached to which products.
        // All pivot fields come directly from v5/products/:id/modifiers/:id.
        Schema::create('product_foodics_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->onDelete('cascade');
            $table->foreignId('foodics_modifier_id')
                  ->constrained('foodics_modifiers')
                  ->onDelete('cascade');

            // How many options the customer must / may select
            $table->unsignedSmallInteger('minimum_options')->nullable();
            $table->unsignedSmallInteger('maximum_options')->nullable();
            // How many options are included in the base price
            $table->unsignedSmallInteger('free_options')->nullable();
            // Option IDs pre-selected by default (Foodics returns these as UUIDs)
            $table->json('default_option_ids')->nullable();
            // Option IDs hidden for this specific product
            $table->json('excluded_option_ids')->nullable();
            // Whether the same option can be chosen more than once
            $table->boolean('unique_options')->default(false);
            // Pizza-style half-half splitting
            $table->boolean('is_splittable_in_half')->default(false);
            // Display order
            $table->unsignedSmallInteger('sort_order')->nullable();

            $table->timestamps();

            $table->unique(['product_id', 'foodics_modifier_id']);
            $table->index('product_id');
            $table->index('foodics_modifier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_foodics_modifiers');
    }
};
