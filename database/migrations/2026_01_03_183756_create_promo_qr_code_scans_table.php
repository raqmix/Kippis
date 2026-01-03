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
        Schema::create('promo_qr_code_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_qr_code_id')->constrained('promo_qr_codes')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->integer('points_awarded'); // Points given in this scan
            $table->dateTime('scanned_at'); // When the scan occurred
            $table->timestamps();

            // Indexes
            $table->index('promo_qr_code_id');
            $table->index('customer_id');
            $table->index(['promo_qr_code_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_qr_code_scans');
    }
};
