<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_pushes', function (Blueprint $table) {
            $table->id();

            // Internal label — the customer never sees this. Ops picks
            // it, uses it to find the row in Filament.
            $table->string('name');

            // Bilingual copy — no `customers.language` column exists, so
            // the batch job concatenates EN + AR the same way
            // NotifyCustomerOnOrderStatusUpdate does today.
            $table->string('title_en');
            $table->string('title_ar');
            $table->text('body_en');
            $table->text('body_ar');

            // day_of_week: 0=Sunday..6=Saturday, matching Carbon::dayOfWeek.
            // NULL means "every day" — the matcher treats null as
            // "wildcard, always match today".
            $table->tinyInteger('day_of_week')->nullable();

            // Wall-clock time in the row's timezone (typically Africa/Cairo).
            $table->time('time_of_day');
            $table->string('timezone')->default('Africa/Cairo');

            // If the console cron misses the exact time (server was down,
            // queue lagged), still fire within N minutes. Beyond that we
            // skip and record a miss — customer would be confused by a
            // 3pm "good morning" reminder.
            $table->unsignedSmallInteger('grace_minutes')->default(30);

            // Where the customer lands when they tap the notification.
            // home: bottom-nav home; offers: the offers surface (built in
            // its own ticket); category/product/offer_id use deeplink_id.
            $table->enum('deeplink_type', [
                'home', 'category', 'product', 'offers', 'offer_id',
            ])->default('home');
            $table->unsignedBigInteger('deeplink_id')->nullable();

            // Audience filters:
            //  all           — every customer with an fcm_token
            //  opted_in_only — respects customers.push_marketing_opt_in
            //  has_ordered   — at least one completed order
            //  inactive_30d  — no completed order in the last 30 days
            $table->enum('audience', [
                'all', 'opted_in_only', 'has_ordered', 'inactive_30d',
            ])->default('opted_in_only');

            $table->boolean('active')->default(true);

            // Rolled forward after every successful fire; used by the
            // Filament UI to show "last sent 2 days ago". Hard dedup lives
            // in scheduled_push_sends (unique on push_id + window_at).
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedInteger('last_sent_count')->default(0);

            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->constrained('admins')
                ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['active', 'day_of_week', 'time_of_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_pushes');
    }
};
