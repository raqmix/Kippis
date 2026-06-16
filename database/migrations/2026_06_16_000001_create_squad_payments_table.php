<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('squad_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('squad_session_id')->constrained('squad_sessions')->cascadeOnDelete();
            $table->foreignId('squad_member_id')->constrained('squad_members')->cascadeOnDelete();

            // Amount this member owes — piasters, same unit as squad_cart_items.unit_price.
            $table->unsignedInteger('share_piasters');

            // pending: row created, waiting on this member to act
            // paying:  member opened MPGS SDK, in flight
            // paid:    PAY succeeded — items belong on the finalized order
            // failed:  decline / SDK error — member can retry
            // skipped: host marked the member skipped from the status board
            // timed_out: deadline hit before member resolved
            // refunded: paid then host cancelled — REFUND succeeded
            $table->enum('status', [
                'pending', 'paying', 'paid', 'failed', 'skipped', 'timed_out', 'refunded',
            ])->default('pending')->index();

            // MPGS bookkeeping — gateway-side IDs we mint at "pay start" time.
            // gateway_order_id is the human-readable order id we hand MPGS (we
            // generate ours; they reuse it across the 3-step flow). transaction_id
            // is the per-attempt id; on retry we issue a fresh one.
            $table->string('gateway_order_id', 64)->nullable()->index();
            $table->string('mastercard_session_id', 128)->nullable();
            $table->string('mastercard_transaction_id', 64)->nullable();

            // Refund bookkeeping — populated when host cancels with paid members.
            $table->string('refund_transaction_id', 64)->nullable();
            $table->timestamp('refunded_at')->nullable();

            // Returned by the verify endpoint so a retried POST returns the same
            // result without reprocessing. UUIDv4 server-generated at pay-start.
            $table->uuid('idempotency_key')->unique();

            $table->timestamp('paid_at')->nullable();
            $table->string('failed_reason', 255)->nullable();

            $table->timestamps();

            // One active row per (session, member). Inactive statuses (failed,
            // skipped, timed_out, refunded) can stack — they're history, not
            // candidates for the finalize step.
            $table->index(['squad_session_id', 'status']);
            $table->index(['squad_session_id', 'squad_member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('squad_payments');
    }
};
