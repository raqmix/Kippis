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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('name_localized')->nullable(); // {en: "Store Name", ar: "اسم المتجر"}
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('receive_online_orders')->default(true);
            $table->string('foodics_branch_id')->nullable()->unique();
            $table->timestamp('synced_from_foodics_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('foodics_branch_id');
            $table->index('is_active');
            $table->index('receive_online_orders');
            $table->index(['is_active', 'receive_online_orders']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
