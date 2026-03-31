<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // foodics_modifier_options maps to v5/modifier_options in Foodics.
        // Each row is an individual selectable choice inside a modifier group
        // (e.g. "Oat Milk", "Large", "Red Sauce") and carries its own price.
        Schema::create('foodics_modifier_options', function (Blueprint $table) {
            $table->id();
            $table->string('foodics_id')->unique();
            $table->foreignId('foodics_modifier_id')
                  ->constrained('foodics_modifiers')
                  ->onDelete('cascade');
            $table->json('name_json');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('sku')->nullable();
            $table->integer('calories')->nullable();
            $table->integer('sort_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('foodics_modifier_id');
            $table->index(['foodics_modifier_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foodics_modifier_options');
    }
};
