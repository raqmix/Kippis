<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->enum('type', ['capture', 'void', 'refund']);
            $table->unsignedInteger('amount')->comment('Amount in piasters');
            $table->enum('gateway', ['mastercard', 'apple_pay', 'cash', 'other'])->default('other');
            $table->string('gateway_reference', 255)->nullable();
            $table->string('gateway_status', 50)->nullable();
            $table->json('gateway_response')->nullable();
            $table->boolean('reconciled')->default(false);
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'created_at']);
            $table->index(['reconciled', 'created_at']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
