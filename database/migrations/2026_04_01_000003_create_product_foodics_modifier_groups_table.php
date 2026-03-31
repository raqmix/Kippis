<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_foodics_modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('foodics_modifier_group_id')->constrained('foodics_modifier_groups', 'id', 'pfmg_fmg_id_foreign')->onDelete('cascade');
            $table->integer('minimum_options')->nullable();
            $table->integer('maximum_options')->nullable();
            $table->integer('free_options')->nullable();
            $table->integer('index')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'foodics_modifier_group_id'], 'pfmg_product_fmg_unique');
            $table->index('product_id', 'pfmg_product_id_index');
            $table->index('foodics_modifier_group_id', 'pfmg_fmg_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_foodics_modifier_groups');
    }
};
