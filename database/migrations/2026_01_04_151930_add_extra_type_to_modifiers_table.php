<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL/MariaDB: Add 'extra' to the ENUM column
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("ALTER TABLE modifiers MODIFY COLUMN type ENUM('size','smothing','customize_modifires','extra') NOT NULL");
        }
        // For SQLite, the column is already a string type, so no alteration needed
        // Validation will be enforced at the application level (Filament form validation)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For MySQL/MariaDB: Remove 'extra' from the ENUM column
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            // Delete any records with 'extra' type before removing it from enum
            DB::table('modifiers')->where('type', 'extra')->delete();
            DB::statement("ALTER TABLE modifiers MODIFY COLUMN type ENUM('size','smothing','customize_modifires') NOT NULL");
        }
        // For SQLite, no reversal needed (it's a string column)
    }
};
