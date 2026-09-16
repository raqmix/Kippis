<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_transactions', function (Blueprint $table) {
            // Only meaningful when type='earned'. The daily expiry
            // command walks earned rows whose expires_at has passed and
            // posts a compensating 'expired' transaction for whatever
            // portion the customer hasn't redeemed yet (FIFO).
            $table->dateTime('expires_at')->nullable()->after('points');
            $table->index('expires_at', 'loyalty_transactions_expires_at_idx');
        });

        // Widen the type enum to a string so we can introduce 'expired'
        // (and any future bookkeeping types) without another enum-change
        // migration. Pre-existing values ('earned', 'redeemed', 'adjusted')
        // are unaffected.
        DB::statement("ALTER TABLE loyalty_transactions MODIFY type VARCHAR(20) NOT NULL");

        // Backfill: every existing earned row gets expires_at = created_at + 1 year.
        // Rows older than a year will be caught by the very first run of
        // the expiry command — which is the honest behaviour even on
        // migration day. Wallets are new at launch so this is mostly a
        // no-op in practice.
        DB::statement("
            UPDATE loyalty_transactions
            SET expires_at = DATE_ADD(created_at, INTERVAL 1 YEAR)
            WHERE type = 'earned' AND expires_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('loyalty_transactions', function (Blueprint $table) {
            $table->dropIndex('loyalty_transactions_expires_at_idx');
            $table->dropColumn('expires_at');
        });
        DB::statement("ALTER TABLE loyalty_transactions MODIFY type ENUM('earned', 'redeemed', 'adjusted') NOT NULL");
    }
};
