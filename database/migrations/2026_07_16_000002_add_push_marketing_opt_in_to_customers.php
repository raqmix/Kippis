<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Default false because Egyptian marketing-consent norms
            // require explicit opt-in. The onboarding flow prompts once
            // and flips this to true if the customer accepts. The
            // "opted_in_only" audience filter on scheduled_pushes
            // respects it; the "all" audience bypasses it (reserved for
            // critical announcements the client insists on sending).
            $table->boolean('push_marketing_opt_in')->default(false)->after('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('push_marketing_opt_in');
        });
    }
};
