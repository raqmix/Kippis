<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL enum extension needs raw SQL — Doctrine can't see enum types.
        // 'awaiting_payments' fires after the host hits Checkout & shares are
        // notified; 'partially_paid' once at least one resolves; 'checked_out'
        // remains the terminal success state.
        DB::statement("
            ALTER TABLE squad_sessions
            MODIFY COLUMN status ENUM(
                'open', 'locked', 'awaiting_payments', 'partially_paid',
                'checked_out', 'cancelled'
            ) NOT NULL DEFAULT 'open'
        ");

        Schema::table('squad_sessions', function (Blueprint $table) {
            $table->timestamp('payment_deadline_at')->nullable()->after('locked_at');
            $table->string('payment_method', 16)->default('card')->after('payment_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::table('squad_sessions', function (Blueprint $table) {
            $table->dropColumn(['payment_deadline_at', 'payment_method']);
        });

        DB::statement("
            ALTER TABLE squad_sessions
            MODIFY COLUMN status ENUM(
                'open', 'locked', 'checked_out', 'cancelled'
            ) NOT NULL DEFAULT 'open'
        ");
    }
};
