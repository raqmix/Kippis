<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wallet pass device subscriptions — Apple's web service spec requires
 * we remember which physical devices have which serial numbers
 * installed so we can fire targeted APNs pushes within the 60s update
 * SLA. Google Wallet doesn't need this table (Google fans out to all
 * devices that saved the object), but we reuse it for both so the
 * audit trail of "who has the pass installed" is unified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_pass_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 10); // 'apple' | 'google'
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Apple-specific identifiers per PassKit web service spec.
            $table->string('device_library_id', 64)->nullable();
            $table->string('pass_type_id', 64)->nullable();
            $table->string('serial_number', 64); // The pass instance id.
            $table->text('push_token')->nullable(); // APNs token for this device+pass.

            // Updated whenever Apple GETs the pass back from us, so the
            // `passesUpdatedSince` filter has something to compare to.
            $table->timestamp('last_updated_at')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'device_library_id', 'serial_number'], 'wp_registrations_unique');
            $table->index(['provider', 'serial_number']);
            $table->index(['customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_pass_registrations');
    }
};
