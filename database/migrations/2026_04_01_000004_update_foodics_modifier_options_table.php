<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foodics_modifier_options', function (Blueprint $table) {
            // Drop old FK and index before renaming
            $table->dropForeign(['foodics_modifier_group_id']);
            $table->dropIndex(['foodics_modifier_group_id']);

            $table->renameColumn('foodics_modifier_group_id', 'foodics_modifier_id');
        });

        Schema::table('foodics_modifier_options', function (Blueprint $table) {
            // Add new columns that the updated sync service writes
            $table->string('sku')->nullable()->after('price');
            $table->integer('calories')->nullable()->after('sku');
            $table->integer('sort_order')->nullable()->after('calories');

            // Re-add FK and indexes with new column name
            $table->foreign('foodics_modifier_id')
                  ->references('id')
                  ->on('foodics_modifiers')
                  ->onDelete('cascade');

            $table->index('foodics_modifier_id');
            $table->index(['foodics_modifier_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('foodics_modifier_options', function (Blueprint $table) {
            $table->dropForeign(['foodics_modifier_id']);
            $table->dropIndex(['foodics_modifier_id']);
            $table->dropIndex(['foodics_modifier_id', 'is_active']);
            $table->dropColumn(['sku', 'calories', 'sort_order']);
            $table->renameColumn('foodics_modifier_id', 'foodics_modifier_group_id');
        });
    }
};
