<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to include 'pending_payment'
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending_payment', 'received', 'mixing', 'ready', 'completed', 'cancelled') DEFAULT 'received'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('received', 'mixing', 'ready', 'completed', 'cancelled') DEFAULT 'received'");
    }
};

