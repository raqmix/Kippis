<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_spend_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();
            $table->foreignId('spend_reward_tier_id')
                ->constrained('spend_reward_tiers')
                ->cascadeOnDelete();

            // Snapshot the tier's threshold at issuance time so a later
            // Filament edit of the tier doesn't retroactively change
            // what this customer earned. Also lets us index the "which
            // repeat crossing this represents" — cycle_index below.
            $table->unsignedBigInteger('threshold_snapshot_piasters');

            // For `cycle=repeating` tiers, 0 = first crossing (2000
            // EGP), 1 = second crossing (4000 EGP), etc. For `once`
            // tiers, always 0. Uniqueness enforced below prevents
            // double-issuing the same crossing.
            $table->unsignedInteger('cycle_index')->default(0);

            // available → picked → applied → redeemed
            //           ↘ expired
            //           ↘ revoked
            //
            // Flow:
            //   available: freshly issued, customer hasn't chosen picks
            //   picked:    customer chose picks_json but hasn't applied
            //              to a cart yet (allows re-picking)
            //   applied:   voucher attached to a cart (cart_id set) —
            //              zero-price items appear in that cart
            //   redeemed:  cart checked out → order created (order_id
            //              set), voucher terminal
            //   expired:   TTL passed, swept nightly
            //   revoked:   ops or a refund reversal invalidated it
            $table->enum('status', [
                'available', 'picked', 'applied', 'redeemed', 'expired', 'revoked',
            ])->default('available')->index();

            // Customer's picks. Nullable until they open the redeem
            // flow and pick a product per choice group. Shape:
            //   [{"label_en": "Main", "product_id": 42},
            //    {"label_en": "Drink", "product_id": 88}]
            $table->json('picks_json')->nullable();

            $table->foreignId('cart_id')
                ->nullable()
                ->constrained('carts')
                ->nullOnDelete();
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();

            $table->timestamps();

            // Prevent double-issuing for the same crossing.
            $table->unique(
                ['customer_id', 'spend_reward_tier_id', 'cycle_index'],
                'unique_customer_tier_cycle',
            );

            // Lookups: rewards screen ("give me the customer's active
            // vouchers"), sweep ("expire everything past its TTL").
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_spend_rewards');
    }
};
