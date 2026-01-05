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
        Schema::table('orders', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['customer_id']);
            
            // Make customer_id nullable
            $table->foreignId('customer_id')->nullable()->change();
            
            // Re-add the foreign key constraint with nullable support
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['customer_id']);
            
            // Make customer_id required again
            $table->foreignId('customer_id')->nullable(false)->change();
            
            // Re-add the foreign key constraint
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');
        });
    }
};

