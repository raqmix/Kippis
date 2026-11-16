<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secret menu (app brief item 10): app_only products are only visible
 * and orderable from the mobile app. Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'app_only')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('app_only')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'app_only')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('app_only');
            });
        }
    }
};
