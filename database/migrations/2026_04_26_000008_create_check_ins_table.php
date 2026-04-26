<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->date('checked_in_at')->index();
            $table->unsignedInteger('streak_count')->default(0);
            $table->unsignedInteger('points_awarded')->default(0);
            $table->enum('reward_type', ['points', 'free_addon'])->default('points');
            $table->json('reward_detail')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
