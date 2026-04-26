<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = Schema::getColumnListing('foodics_modifier_options');

        // Only rename if the old column still exists (idempotent)
        if (in_array('foodics_modifier_group_id', $columns)) {
            // On SQLite, foreign key/index drops are unreliable; skip them.
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('foodics_modifier_options', function (Blueprint $table) {
                    $table->dropForeign(['foodics_modifier_group_id']);
                    $table->dropIndex(['foodics_modifier_group_id']);
                });
            }

            Schema::table('foodics_modifier_options', function (Blueprint $table) {
                $table->renameColumn('foodics_modifier_group_id', 'foodics_modifier_id');
            });
        }

        Schema::table('foodics_modifier_options', function (Blueprint $table) use ($columns) {
            if (! in_array('sku', $columns)) {
                $table->string('sku')->nullable()->after('price');
            }
            if (! in_array('calories', $columns)) {
                $table->integer('calories')->nullable()->after('sku');
            }
            if (! in_array('sort_order', $columns)) {
                $table->integer('sort_order')->nullable()->after('calories');
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('foodics_modifier_options', function (Blueprint $table) {
                $table->foreign('foodics_modifier_id')
                      ->references('id')
                      ->on('foodics_modifiers')
                      ->onDelete('cascade');

                $table->index('foodics_modifier_id');
                $table->index(['foodics_modifier_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('foodics_modifier_options', function (Blueprint $table) {
                $table->dropForeign(['foodics_modifier_id']);
                $table->dropIndex(['foodics_modifier_id']);
                $table->dropIndex(['foodics_modifier_id', 'is_active']);
            });
        }

        Schema::table('foodics_modifier_options', function (Blueprint $table) {
            $table->dropColumn(['sku', 'calories', 'sort_order']);
            $table->renameColumn('foodics_modifier_id', 'foodics_modifier_group_id');
        });
    }
};
