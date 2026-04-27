<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviter_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('invitee_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('referral_code', 30)->unique()->index();
            $table->enum('status', ['pending', 'registered', 'converted'])->default('pending');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->unsignedInteger('inviter_points')->default(0);
            $table->unsignedInteger('invitee_points')->default(0);
            $table->timestamps();

            $table->index(['inviter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
