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
        Schema::create('promo_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Unique code string embedded in QR (e.g., "PROMO-ABC123")
            $table->string('name'); // Admin-friendly name/description
            $table->integer('points'); // Points awarded when scanned
            $table->boolean('is_active')->default(true); // Enable/disable QR code
            $table->dateTime('available_from'); // When scanning becomes available
            $table->dateTime('expires_at')->nullable(); // When QR code expires
            $table->integer('max_uses_per_customer')->nullable(); // Max times one customer can use (null = unlimited)
            $table->integer('max_total_uses')->nullable(); // Max total scans across all customers (null = unlimited)
            $table->integer('total_uses_count')->default(0); // Track total number of scans
            $table->foreignId('created_by')->nullable()->constrained('admins')->onDelete('set null'); // Admin who created it
            $table->timestamps();

            // Indexes
            $table->index('code');
            $table->index('is_active');
            $table->index('available_from');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_qr_codes');
    }
};
