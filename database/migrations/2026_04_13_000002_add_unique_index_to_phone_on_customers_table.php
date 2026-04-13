<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a unique index on the phone column.
     *
     * MySQL and SQLite both allow multiple NULL values in a unique index, so
     * social-login accounts without a phone number are unaffected.
     *
     * Before adding the unique constraint we drop the existing plain index to
     * avoid "duplicate key name" errors, and we deduplicate any conflicting
     * rows by keeping the oldest (lowest id) record and nulling out the phone
     * on the newer duplicates so the constraint can be applied safely.
     */
    public function up(): void
    {
        // 1. Null out duplicate phone values, keeping the oldest account.
        //    This is a no-op on clean databases.
        \DB::statement('
            UPDATE customers c1
            JOIN (
                SELECT phone, MIN(id) AS keep_id
                FROM customers
                WHERE phone IS NOT NULL
                GROUP BY phone
                HAVING COUNT(*) > 1
            ) dupes ON c1.phone = dupes.phone AND c1.id != dupes.keep_id
            SET c1.phone = NULL
        ');

        Schema::table('customers', function (Blueprint $table) {
            // Drop the old plain index before replacing it with a unique one.
            try {
                $table->dropIndex(['phone']);
            } catch (\Exception $e) {
                // Index may already be gone – safe to ignore.
            }

            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->index('phone');
        });
    }
};
