<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            
            // Security fields
            $table->boolean('two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            
            // Tracking
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->text('last_user_agent')->nullable();
            
            // Access Control
            $table->json('allowed_ips')->nullable();
            $table->time('access_start_time')->nullable();
            $table->time('access_end_time')->nullable();
            $table->json('allowed_days')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            
            // Locale
            $table->string('locale', 2)->default('en');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('email');
            $table->index('is_active');
            $table->index('two_factor_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
