<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('name_edited_at');
        });
    }
};
