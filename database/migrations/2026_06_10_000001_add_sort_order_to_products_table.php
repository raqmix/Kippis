<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active');
                $table->index('sort_order');
            }
        });

        // Seed an initial order so existing rows keep a stable sequence.
        DB::table('products')->orderBy('id')->update([
            'sort_order' => DB::raw('id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'sort_order')) {
                $table->dropIndex(['sort_order']);
                $table->dropColumn('sort_order');
            }
        });
    }
};
