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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->json('name_json'); // {ar: "...", en: "..."}
            $table->json('description_json')->nullable(); // {ar: "...", en: "..."}
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('external_source', ['local', 'foodics'])->default('local');
            $table->string('foodics_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('external_source');
            $table->index('foodics_id');
            $table->index('is_active');
            $table->index(['external_source', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

