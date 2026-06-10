<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each branch (store) carries its own Foodics menu group — the group of
 * products available at that branch. The catalog sync now iterates each
 * store with this column set and pulls its menu group's products
 * specifically, instead of pulling the global product list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('foodics_menu_group_id')->nullable()->after('foodics_branch_id');
            $table->index('foodics_menu_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['foodics_menu_group_id']);
            $table->dropColumn('foodics_menu_group_id');
        });
    }
};
