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
        // Delete existing records with old type values since they're no longer valid
        DB::table('modifiers')
            ->whereIn('type', ['sweetness', 'fizz', 'caffeine', 'extra'])
            ->delete();
        
        // For MySQL/MariaDB: Alter the ENUM column to only include new values
        // For SQLite: No need to alter (it's stored as string, validation is application-level)
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("ALTER TABLE modifiers MODIFY COLUMN type ENUM('size','smothing','customize_modifires') NOT NULL");
        }
        // For SQLite, the column is already a string type, so no alteration needed
        // Validation will be enforced at the application level (Filament form validation)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For MySQL/MariaDB: Restore the old ENUM values
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("ALTER TABLE modifiers MODIFY COLUMN type ENUM('sweetness','fizz','caffeine','extra') NOT NULL");
        }
        // For SQLite, no reversal needed (it's a string column)
    }
};
