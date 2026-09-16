<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_push_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_push_id')
                ->constrained('scheduled_pushes')
                ->cascadeOnDelete();

            // The scheduled "target datetime" in UTC — for a Thursday-8am
            // Cairo row, this is that Thursday's 08:00 Cairo converted to
            // UTC. Unique with push_id so a repeated cron run can't fire
            // the same window twice (the fire path uses firstOrCreate on
            // this pair — the loser skips silently).
            $table->timestamp('window_at');

            // When the cron actually fired the send.
            $table->timestamp('fired_at');

            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_ok_count')->default(0);
            $table->unsignedInteger('sent_failed_count')->default(0);

            $table->timestamps();

            $table->unique(['scheduled_push_id', 'window_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_push_sends');
    }
};
