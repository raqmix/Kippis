<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Replace any plaintext kiosk API key with its SHA-256 hash. Existing
        // kiosks keep working without reconfiguration: they still send the same
        // plaintext key and KioskAuth compares its hash. Values already stored as
        // a 64-char hex hash are left untouched so this is safe to re-run.
        DB::table('stores')->whereNotNull('kiosk_api_key')->orderBy('id')->each(function ($store) {
            $key = $store->kiosk_api_key;

            if (preg_match('/^[0-9a-f]{64}$/', (string) $key)) {
                return; // already hashed
            }

            DB::table('stores')
                ->where('id', $store->id)
                ->update(['kiosk_api_key' => hash('sha256', (string) $key)]);
        });
    }

    public function down(): void
    {
        // Hashes are irreversible; nothing to restore.
    }
};
