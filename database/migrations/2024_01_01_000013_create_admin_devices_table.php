<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
            $table->string('device_hash')->unique();
            $table->string('device_name')->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent');
            $table->string('browser_fingerprint')->nullable();
            $table->boolean('is_trusted')->default(false);
            $table->timestamp('last_used_at');
            $table->timestamps();
            
            $table->index(['admin_id', 'device_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_devices');
    }
};

