<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending_payment', 'received', 'mixing', 'ready', 'completed', 'cancelled') DEFAULT 'received'");
        }
    }
    public function down(): void {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('received', 'mixing', 'ready', 'completed', 'cancelled') DEFAULT 'received'");
        }
    }
};
