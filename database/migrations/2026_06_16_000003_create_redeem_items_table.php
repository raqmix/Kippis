<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redeem_items', function (Blueprint $table) {
            $table->id();

            // Linked product the redemption grants (e.g. "Flat White").
            // Nullable so admins can run promo redemptions for stuff
            // that's not a catalog product (a t-shirt, a discount code).
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            // Localized title + description. Match the same JSON shape
            // we use for products/categories so the Filament tabbed
            // form / mobile resolver pattern just works.
            $table->json('title_json');
            $table->json('description_json')->nullable();

            $table->string('image')->nullable();

            // What it costs the customer. Always integer points; rates
            // and EGP conversions live in Settings.
            $table->unsignedInteger('points_cost');

            // Caps so we can throttle hot rewards without disabling them.
            // null = no cap.
            $table->unsignedInteger('max_per_customer_lifetime')->nullable();
            $table->unsignedInteger('max_per_customer_per_day')->nullable();
            $table->unsignedInteger('max_global')->nullable();

            // Time-limit a redeemed wallet item — null = no expiry.
            // Mirrored by Settings default `loyalty.wallet_item_ttl_days`
            // when not set per-item; per-item value wins.
            $table->unsignedSmallInteger('wallet_ttl_days')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redeem_items');
    }
};
