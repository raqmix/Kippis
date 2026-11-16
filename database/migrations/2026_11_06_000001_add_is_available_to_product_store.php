<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-branch availability on the product_store pivot. A row now means
 * "this product is on this branch's menu"; is_available=false means the
 * branch has switched it off in Foodics (per-branch is_active=false or
 * is_in_stock=false), so the app hides it and checkout rejects it.
 * Existing rows default to available, matching today's behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_store', function (Blueprint $table) {
            $table->boolean('is_available')->default(true)->after('store_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_store', function (Blueprint $table) {
            $table->dropColumn('is_available');
        });
    }
};
