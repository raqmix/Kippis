<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set when an admin edits a store's name in the Kippis admin. The Foodics
     * branch sync then leaves name/name_localized alone for that store.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->timestamp('name_edited_at')->nullable()->after('name_localized');
        });

        // Existing names may already have been edited by an admin, and we
        // can't tell which. Treat them all as admin-owned so the first sync
        // after this deploy renames nothing; "Use Foodics name again" on the
        // store page hands a name back to Foodics.
        DB::table('stores')->update(['name_edited_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('name_edited_at');
        });
    }
};
