<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('foodics_order_id')->nullable()->after('gateway_order_id');
            $table->timestamp('foodics_pushed_at')->nullable()->after('foodics_order_id');
            $table->index('foodics_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['foodics_order_id']);
            $table->dropColumn(['foodics_order_id', 'foodics_pushed_at']);
        });
    }
};
