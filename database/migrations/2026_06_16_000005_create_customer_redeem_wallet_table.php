<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per claimed reward. Status transitions:
     *   available  → applied  (customer picks at cart, deducted via discount)
     *   available  → expired  (TTL elapsed without use)
     *   available  → refunded (admin / refund flow, points returned)
     */
    public function up(): void
    {
        Schema::create('customer_redeem_wallet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('redeem_item_id')->constrained('redeem_items')->cascadeOnDelete();
            $table->unsignedInteger('points_spent');

            $table->enum('status', ['available', 'applied', 'expired', 'refunded'])
                ->default('available')->index();

            $table->timestamp('expires_at')->nullable();
            $table->foreignId('used_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('used_at')->nullable();

            // Snapshot the redeem item's title at claim time so historical
            // wallet entries stay readable even if the redeem item is
            // edited or removed.
            $table->json('title_snapshot_json')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_redeem_wallet');
    }
};
