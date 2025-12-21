<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('channel_id')->nullable()->constrained('channels')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['is_active', 'channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
