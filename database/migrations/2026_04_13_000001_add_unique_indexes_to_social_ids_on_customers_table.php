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
        Schema::table('customers', function (Blueprint $table) {
            // Drop plain indexes first (ignore if they don't exist).
            try { $table->dropIndex(['google_id']); } catch (\Exception) {}
            try { $table->dropIndex(['apple_id']); } catch (\Exception) {}

            $table->unique('google_id');
            $table->unique('apple_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            try { $table->dropUnique(['google_id']); } catch (\Exception) {}
            try { $table->dropUnique(['apple_id']); } catch (\Exception) {}

            $table->index('google_id');
            $table->index('apple_id');
        });
    }
};
