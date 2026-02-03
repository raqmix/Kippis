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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
            $table->string('country_code', 5)->nullable()->change();
            $table->date('birthdate')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
            $table->string('country_code', 5)->nullable(false)->change();
            $table->date('birthdate')->nullable(false)->change();
        });
    }
};
