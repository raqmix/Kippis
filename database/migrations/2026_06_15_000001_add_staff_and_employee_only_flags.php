<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee-only stores: branches like Factory should only appear in the app
 * for staff customers (baristas / ops). The list endpoint filters them out
 * for everyone else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_staff')->default(false)->after('is_verified');
            $table->index('is_staff');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('is_employee_only')->default(false)->after('receive_online_orders');
            $table->index('is_employee_only');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['is_staff']);
            $table->dropColumn('is_staff');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['is_employee_only']);
            $table->dropColumn('is_employee_only');
        });
    }
};
