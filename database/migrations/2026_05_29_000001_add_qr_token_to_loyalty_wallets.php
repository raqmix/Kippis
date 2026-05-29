<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_wallets', function (Blueprint $table) {
            $table->string('qr_token')->nullable()->unique()->after('points');
        });

        // Backfill a non-enumerable token for existing wallets.
        DB::table('loyalty_wallets')->whereNull('qr_token')->orderBy('id')->each(function ($wallet) {
            DB::table('loyalty_wallets')
                ->where('id', $wallet->id)
                ->update(['qr_token' => (string) Str::uuid()]);
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_wallets', function (Blueprint $table) {
            $table->dropColumn('qr_token');
        });
    }
};
