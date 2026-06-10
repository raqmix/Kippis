<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Newly-pulled Foodics products land as drafts so admins explicitly opt
 * each one in before customers can see it. The customer-facing
 * `Product::scopeActive()` adds `is_draft = false` to the existing
 * `is_active = true` filter, so a draft product is invisible to kiosk +
 * customer app without needing a separate scope. Existing products are
 * not retroactively flagged — only newly-synced ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('is_active');
            $table->index('is_draft');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_draft']);
            $table->dropColumn('is_draft');
        });
    }
};
