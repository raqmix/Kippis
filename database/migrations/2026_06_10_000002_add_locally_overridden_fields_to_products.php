<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track which fields on a Foodics-synced product the admin has hand-edited
 * in Filament, so the recurring catalog sync skips overwriting them. The
 * column is a JSON array of field names — empty/null means "no overrides,
 * sync everything as before". Local-only products ignore it entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('locally_overridden_fields')->nullable()->after('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('locally_overridden_fields');
        });
    }
};
