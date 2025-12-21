<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->onDelete('set null');
            $table->string('event_type')->index(); // Values: failed_login, suspicious_activity, privilege_escalation, data_breach_attempt, ip_blocked, rate_limit_exceeded, unauthorized_access, configuration_change, password_changed, 2fa_enabled, 2fa_disabled, account_locked
            $table->string('severity')->default('medium'); // Values: low, medium, high, critical
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('admins')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['severity', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['resolved', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
