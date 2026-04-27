<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creators', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 100);
            $table->string('name_ar', 100);
            $table->text('bio_en')->nullable();
            $table->text('bio_ar')->nullable();
            $table->string('avatar', 500)->nullable();
            $table->string('social_handle', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('creator_drops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('creators')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('title_en', 200);
            $table->string('title_ar', 200);
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('cover_image', 500)->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->enum('status', ['draft', 'scheduled', 'live', 'ended', 'cancelled'])->default('draft');
            $table->unsignedInteger('notify_before_minutes')->default(60);
            $table->boolean('notification_sent')->default(false);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete();
            $table->json('store_ids')->nullable()->comment('null = all stores');
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_drops');
        Schema::dropIfExists('creators');
    }
};
