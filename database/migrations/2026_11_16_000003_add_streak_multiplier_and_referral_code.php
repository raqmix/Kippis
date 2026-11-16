<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * App brief section C: double-points rules, order streak, reusable
 * Bring-a-Friend code.
 *
 *   points_multiplier_rules   admin-managed "Happy Hour" / "Monday Refill" rules
 *   customers.order_streak_*  consecutive Cairo days with an app order
 *   customers.referral_code   one reusable personal code per customer,
 *                             backfilled from each inviter's existing code
 *   referrals.referral_code   no longer unique: one row per invitee, all
 *                             sharing the inviter's code
 *
 * Additive and guarded so it runs on a fresh install and on prod's schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('points_multiplier_rules')) {
            Schema::create('points_multiplier_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->decimal('multiplier', 4, 2)->default(2);
                $table->json('days_of_week')->nullable();   // ISO 1 (Mon) - 7 (Sun); null = every day
                $table->time('time_from')->nullable();      // Cairo wall clock; null = all day
                $table->time('time_to')->nullable();
                $table->date('starts_at')->nullable();      // inclusive Cairo dates
                $table->date('ends_at')->nullable();
                $table->json('platforms')->nullable();      // null = every app platform
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'order_streak_count')) {
                $table->unsignedSmallInteger('order_streak_count')->default(0);
            }
            if (! Schema::hasColumn('customers', 'order_streak_last_date')) {
                $table->date('order_streak_last_date')->nullable();
            }
            if (! Schema::hasColumn('customers', 'referral_code')) {
                $table->string('referral_code', 30)->nullable()->unique();
            }
        });

        $this->dropReferralCodeUnique();
        $this->backfillCustomerReferralCodes();
    }

    public function down(): void
    {
        Schema::dropIfExists('points_multiplier_rules');

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'referral_code')) {
                $table->dropUnique(['referral_code']);
            }
            $cols = array_values(array_filter(
                ['order_streak_count', 'order_streak_last_date', 'referral_code'],
                fn ($c) => Schema::hasColumn('customers', $c),
            ));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
        // referrals.referral_code stays non-unique: rows may already share codes.
    }

    private function dropReferralCodeUnique(): void
    {
        if (! Schema::hasTable('referrals')) {
            return;
        }

        foreach (Schema::getIndexes('referrals') as $index) {
            if (! empty($index['unique']) && $index['columns'] === ['referral_code']) {
                $name = $index['name'];
                Schema::table('referrals', fn (Blueprint $table) => $table->dropUnique($name));
            }
        }

        $hasPlainIndex = collect(Schema::getIndexes('referrals'))
            ->contains(fn ($i) => $i['columns'] === ['referral_code']);
        if (! $hasPlainIndex) {
            Schema::table('referrals', fn (Blueprint $table) => $table->index('referral_code'));
        }
    }

    /**
     * Each inviter keeps the code they are already sharing: their open
     * (pending, no invitee) code if any, else their oldest one.
     */
    private function backfillCustomerReferralCodes(): void
    {
        if (! Schema::hasTable('referrals')) {
            return;
        }

        $taken = DB::table('customers')->whereNotNull('referral_code')->pluck('referral_code')
            ->map(fn ($c) => strtoupper($c))->flip()->all();
        $assigned = [];

        DB::table('referrals')
            ->select('id', 'inviter_id', 'referral_code', 'status', 'invitee_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$assigned) {
                foreach ($rows as $row) {
                    $open = $row->status === 'pending' && $row->invitee_id === null;
                    $current = $assigned[$row->inviter_id] ?? null;
                    if ($current === null || ($open && ! $current['open'])) {
                        $assigned[$row->inviter_id] = ['code' => $row->referral_code, 'open' => $open];
                    }
                }
            });

        foreach ($assigned as $inviterId => $entry) {
            $code = strtoupper($entry['code']);
            if (isset($taken[$code])) {
                continue;
            }
            $updated = DB::table('customers')
                ->where('id', $inviterId)
                ->whereNull('referral_code')
                ->update(['referral_code' => $code]);
            if ($updated) {
                $taken[$code] = true;
            }
        }
    }
};
