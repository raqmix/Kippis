<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_slots', function (Blueprint $table) {
            $table->id();
            $table->string('slot_key', 50)->unique();
            $table->string('title_en', 200);
            $table->string('title_ar', 200);
            $table->text('subtitle_en')->nullable();
            $table->text('subtitle_ar')->nullable();
            $table->string('image', 500)->nullable();
            $table->string('cta_text_en', 100)->nullable();
            $table->string('cta_text_ar', 100)->nullable();
            $table->json('cta_action')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('platform')->nullable()->comment('Array of platforms: web, mobile, kiosk');
            $table->timestamps();

            $table->index(['slot_key', 'is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_slots');
    }
};
