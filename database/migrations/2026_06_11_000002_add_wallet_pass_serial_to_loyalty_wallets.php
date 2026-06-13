<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Each wallet gets a single serial number reused across both Apple and
 * Google pass providers. The qr_token already plays the role of a
 * scannable opaque id; the wallet_pass_serial is the *pass instance*
 * id Apple's web service uses inside URLs. They're separate so we can
 * rotate one without invalidating the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_wallets', function (Blueprint $table) {
            $table->string('wallet_pass_serial', 32)
                ->nullable()
                ->unique()
                ->after('qr_token');
        });

        // Backfill so every existing wallet already has a serial when
        // the customer hits "Add to Wallet" for the first time.
        DB::table('loyalty_wallets')
            ->whereNull('wallet_pass_serial')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('loyalty_wallets')
                    ->where('id', $row->id)
                    ->update([
                        'wallet_pass_serial' => Str::lower(Str::random(24)),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('loyalty_wallets', function (Blueprint $table) {
            $table->dropUnique(['wallet_pass_serial']);
            $table->dropColumn('wallet_pass_serial');
        });
    }
};
