<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('refunded_amount')
                ->default(0)
                ->after('discount')
                ->comment('Total refunded in piasters');
            $table->string('gateway_order_id', 255)
                ->nullable()
                ->after('refunded_amount')
                ->comment('Gateway-side order ID for refund/void calls');
        });

        // Add refund_status enum (MySQL only; SQLite uses TEXT)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders ADD COLUMN refund_status ENUM('none','partial','full','voided') NOT NULL DEFAULT 'none' AFTER refunded_amount");
        } else {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('refund_status', 10)->default('none')->after('refunded_amount');
            });
        }

        // Extend status enum to include 'voided' and 'pending_payment' (MySQL only)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('received','mixing','ready','completed','cancelled','voided','pending_payment') NOT NULL DEFAULT 'received'");
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['refunded_amount', 'gateway_order_id', 'refund_status']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('received','mixing','ready','completed','cancelled') NOT NULL DEFAULT 'received'");
        }
    }
};
