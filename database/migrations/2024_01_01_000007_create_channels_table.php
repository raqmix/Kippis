<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type')->default('api'); // Values: pos, payment, webhook, api
            $table->string('status')->default('inactive'); // Values: active, inactive
            $table->text('credentials')->nullable(); // Encrypted JSON
            $table->json('settings')->nullable();
            $table->string('webhook_url')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
