<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_push_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('category', 20); // marketing | transactional
            $table->string('status', 20);   // sent | skipped | failed
            $table->string('reason', 120)->nullable(); // why skipped / failed
            // Only set on rows that consumed the key (sent). A failed or skipped
            // attempt frees the key so a later run may retry.
            $table->string('dedup_key', 120)->nullable();
            $table->string('attempt_key', 120)->nullable(); // the key that was requested, kept for audit
            $table->timestamp('sent_at')->useCurrent(); // time of the attempt
            $table->timestamps();

            $table->unique(['customer_id', 'dedup_key']);
            $table->index(['customer_id', 'category', 'status', 'sent_at'], 'cpl_cap_lookup');
            $table->index(['type', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_push_log');
    }
};
