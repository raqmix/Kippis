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
        Schema::create('notifications_center', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('admins')->onDelete('cascade');
            $table->string('type'); // new_booking, low_stock, staff_absence, new_reviews
            $table->string('title');
            $table->text('body');
            $table->string('icon')->default('heroicon-o-bell'); // heroicon name
            $table->string('color')->default('light-green'); // default light green
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->string('action_url')->nullable(); // Filament route
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications_center');
    }
};
