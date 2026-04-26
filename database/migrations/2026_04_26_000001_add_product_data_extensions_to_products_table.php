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
            if (! Schema::hasColumn('products', 'allergens')) {
                $table->json('allergens')->nullable()->after('product_kind');
            }
            if (! Schema::hasColumn('products', 'caffeine_mg')) {
                $table->unsignedInteger('caffeine_mg')->nullable()->after('allergens');
            }
            if (! Schema::hasColumn('products', 'caffeine_level')) {
                $table->enum('caffeine_level', ['none', 'low', 'medium', 'high'])->nullable()->after('caffeine_mg');
            }
        });

        // Extend product_kind enum to add 'combo' (MySQL only; SQLite stores as TEXT so no ALTER needed)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE products MODIFY COLUMN product_kind ENUM('regular', 'mix_base', 'combo') NOT NULL DEFAULT 'regular'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE products MODIFY COLUMN product_kind ENUM('regular', 'mix_base') NOT NULL DEFAULT 'regular'");
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['allergens', 'caffeine_mg', 'caffeine_level']);
        });
    }
};
