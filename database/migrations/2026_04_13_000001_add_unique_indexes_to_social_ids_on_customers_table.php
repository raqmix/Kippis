<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add unique indexes to google_id and apple_id so one social identity
     * can only ever belong to one customer row.
     *
     * MySQL / SQLite allow multiple NULL values in a unique index, so
     * customers that have never used social login are unaffected.
     */
    public function up(): void
    {
        // Drop the plain (non-unique) indexes added in an earlier migration
        // before replacing them, to avoid "duplicate key name" errors.
        Schema::table('customers', function (Blueprint $table) {
            // dropIndex is a no-op if the index doesn't exist in some drivers,
            // so we check before dropping to keep things safe.
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = array_keys($sm->listTableIndexes('customers'));

            if (in_array('customers_google_id_index', $indexes)) {
                $table->dropIndex(['google_id']);
            }
            if (in_array('customers_apple_id_index', $indexes)) {
                $table->dropIndex(['apple_id']);
            }

            $table->unique('google_id');
            $table->unique('apple_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropUnique(['apple_id']);

            $table->index('google_id');
            $table->index('apple_id');
        });
    }
};
