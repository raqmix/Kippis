<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spend_reward_tiers', function (Blueprint $table) {
            $table->id();

            // Ops-editable label + description for the customer-facing
            // rewards screen. Bilingual.
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();

            // Threshold customers must cross to earn this reward. In
            // piasters so it matches customers.lifetime_spent_piasters
            // without conversion.
            $table->unsignedBigInteger('threshold_piasters');

            // How this tier repeats:
            //   once      — earned once per customer, ever
            //   repeating — earned every time cumulative spend crosses
            //               another <threshold_piasters> (e.g. every
            //               2000 EGP earns another sandwich+drink)
            $table->enum('cycle', ['once', 'repeating'])->default('once');

            // Reward shape. Ops picks one or more "choice groups" per
            // tier; each group defines a label + eligible categories +
            // quantity. At redemption time the customer picks one
            // product from any eligible category in each group.
            //
            // Shape (validated at Filament + service layer):
            //   [
            //     {"label_en": "Main", "label_ar": "الوجبة الرئيسية",
            //      "category_ids": [3, 5], "quantity": 1},
            //     {"label_en": "Drink", "label_ar": "المشروب",
            //      "category_ids": [8], "quantity": 1}
            //   ]
            $table->json('choice_groups');

            // Voucher expiry once issued. Null = never expires.
            $table->unsignedInteger('voucher_ttl_days')->nullable();

            // Optional validity window on the tier itself — outside
            // this range the checker skips the tier entirely (useful
            // for time-limited campaigns).
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spend_reward_tiers');
    }
};
