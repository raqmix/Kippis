<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rewards wallet (app brief item 6): customer_redeem_wallet now also holds
 * gifts issued by the system (streak, referral, birthday, admin), not only
 * items bought with points.
 *
 *   redeem_item_id   now nullable (issued gifts have no redeem item)
 *   source           redeem|streak|referral|birthday|admin
 *   source_ref       idempotency key per (customer, source), e.g. "2026-W46"
 *   reward_type      product (linked product's price off) |
 *                    free_item (most expensive qualifying line, capped) |
 *                    counter (shown as QR, redeemed by the cashier)
 *   reward_config    e.g. {"category_ids":[3,4],"max_value":130}
 *   code             unique short code for QR / cashier lookup
 *   used_by_admin_id admin who marked a counter gift as used
 *
 * Existing rows: source=redeem; reward_type=product when the redeem item
 * links a product, else counter. Every row gets a code.
 */
return new class extends Migration
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function up(): void
    {
        Schema::table('customer_redeem_wallet', function (Blueprint $table) {
            $table->unsignedBigInteger('redeem_item_id')->nullable()->change();
        });

        Schema::table('customer_redeem_wallet', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_redeem_wallet', 'source')) {
                $table->string('source', 20)->default('redeem')->after('redeem_item_id');
            }
            if (! Schema::hasColumn('customer_redeem_wallet', 'source_ref')) {
                $table->string('source_ref', 64)->nullable()->after('source');
            }
            if (! Schema::hasColumn('customer_redeem_wallet', 'reward_type')) {
                $table->string('reward_type', 20)->default('product')->after('source_ref');
            }
            if (! Schema::hasColumn('customer_redeem_wallet', 'reward_config')) {
                $table->json('reward_config')->nullable()->after('reward_type');
            }
            if (! Schema::hasColumn('customer_redeem_wallet', 'code')) {
                $table->string('code', 16)->nullable()->after('reward_config');
            }
            if (! Schema::hasColumn('customer_redeem_wallet', 'used_by_admin_id')) {
                $table->unsignedBigInteger('used_by_admin_id')->nullable()->after('used_at');
            }
        });

        // Backfill reward_type for legacy rows: product when the redeem
        // item links a product, otherwise a counter gift.
        DB::table('customer_redeem_wallet')
            ->whereNotNull('redeem_item_id')
            ->whereIn('redeem_item_id', DB::table('redeem_items')->whereNull('product_id')->select('id'))
            ->update(['reward_type' => 'counter']);

        DB::table('customer_redeem_wallet')->whereNull('source')->update(['source' => 'redeem']);

        // Every entry gets a unique code.
        $used = DB::table('customer_redeem_wallet')->whereNotNull('code')->pluck('code')->flip()->all();
        DB::table('customer_redeem_wallet')->whereNull('code')->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($rows) use (&$used) {
                foreach ($rows as $row) {
                    do {
                        $code = $this->randomCode();
                    } while (isset($used[$code]));
                    $used[$code] = true;
                    DB::table('customer_redeem_wallet')->where('id', $row->id)->update(['code' => $code]);
                }
            });

        Schema::table('customer_redeem_wallet', function (Blueprint $table) {
            if (! Schema::hasIndex('customer_redeem_wallet', 'customer_redeem_wallet_code_unique')) {
                $table->unique('code');
            }
            if (! Schema::hasIndex('customer_redeem_wallet', 'crw_customer_source_ref_unique')) {
                // NULL source_ref never collides, so redeem claims stay unlimited.
                $table->unique(['customer_id', 'source', 'source_ref'], 'crw_customer_source_ref_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_redeem_wallet', function (Blueprint $table) {
            if (Schema::hasIndex('customer_redeem_wallet', 'crw_customer_source_ref_unique')) {
                $table->dropUnique('crw_customer_source_ref_unique');
            }
            if (Schema::hasIndex('customer_redeem_wallet', 'customer_redeem_wallet_code_unique')) {
                $table->dropUnique('customer_redeem_wallet_code_unique');
            }
            $cols = array_values(array_filter(
                ['source', 'source_ref', 'reward_type', 'reward_config', 'code', 'used_by_admin_id'],
                fn ($c) => Schema::hasColumn('customer_redeem_wallet', $c),
            ));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
        // redeem_item_id stays nullable: issued gifts may exist by now.
    }

    private function randomCode(): string
    {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
};
