<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('google_id')->nullable()->after('foodics_customer_id');
            $table->text('google_refresh_token')->nullable()->after('google_id');
            $table->string('apple_id')->nullable()->after('google_refresh_token');
            $table->text('apple_refresh_token')->nullable()->after('apple_id');
            $table->string('social_avatar')->nullable()->after('apple_refresh_token');

            $table->index('google_id');
            $table->index('apple_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['google_id']);
            $table->dropIndex(['apple_id']);
            $table->dropColumn(['google_id', 'google_refresh_token', 'apple_id', 'apple_refresh_token', 'social_avatar']);
        });
    }
};
