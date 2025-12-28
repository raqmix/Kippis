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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->json('name_json'); // {ar: "...", en: "..."}
            $table->json('description_json')->nullable(); // {ar: "...", en: "..."}
            $table->string('image')->nullable();
            $table->decimal('base_price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->enum('external_source', ['local', 'foodics'])->default('local');
            $table->string('foodics_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('category_id');
            $table->index('external_source');
            $table->index('foodics_id');
            $table->index('is_active');
            $table->index(['category_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

