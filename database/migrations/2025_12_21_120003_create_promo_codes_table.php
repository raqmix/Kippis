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
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 10, 2);
            $table->dateTime('valid_from');
            $table->dateTime('valid_to');
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_per_user_limit')->nullable();
            $table->integer('used_count')->default(0);
            $table->decimal('minimum_order_amount', 10, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('code');
            $table->index('active');
            $table->index(['valid_from', 'valid_to']);
            $table->index(['active', 'valid_from', 'valid_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};

