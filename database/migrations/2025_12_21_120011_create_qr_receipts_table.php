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
        Schema::create('qr_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('set null');
            $table->string('receipt_number');
            $table->string('receipt_image')->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->integer('points_awarded')->nullable();
            $table->integer('points_requested');
            $table->json('meta')->nullable(); // Store receipt data
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->onDelete('set null');
            $table->timestamps();

            // Indexes
            $table->index('customer_id');
            $table->index('status');
            $table->index('receipt_number');
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_receipts');
    }
};

