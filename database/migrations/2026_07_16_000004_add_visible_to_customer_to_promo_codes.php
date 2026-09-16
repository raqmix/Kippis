<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            // Whether this promo code should appear in the customer-facing
            // "Offers" surface (Flutter OffersListScreen + web /offers).
            // Default false — most promo_codes are internal auto-promos
            // (staff discounts, first-order silent gifts, etc.) that the
            // client doesn't want to advertise. Ops flips this on for
            // the offers they want browsable.
            $table->boolean('visible_to_customer')->default(false)->after('active');
            $table->index('visible_to_customer');
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropIndex(['visible_to_customer']);
            $table->dropColumn('visible_to_customer');
        });
    }
};
