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
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('loyalty_wallets')->onDelete('cascade');
            $table->enum('type', ['earned', 'redeemed', 'adjusted']);
            $table->integer('points'); // Can be positive or negative
            $table->text('description')->nullable();
            $table->string('reference_type')->nullable(); // e.g., 'order', 'qr_receipt'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->onDelete('set null');
            $table->timestamps();

            // Indexes
            $table->index('wallet_id');
            $table->index('type');
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};

