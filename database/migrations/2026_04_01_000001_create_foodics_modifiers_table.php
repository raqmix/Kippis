<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // foodics_modifiers maps to v5/modifiers in the Foodics API.
        // In Foodics terminology a "modifier" is the group container
        // (e.g. "Milk Type", "Size", "Sauce").  The selectable choices
        // inside it are "modifier options" stored in foodics_modifier_options.
        Schema::create('foodics_modifiers', function (Blueprint $table) {
            $table->id();
            $table->string('foodics_id')->unique();
            $table->json('name_json');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foodics_modifiers');
    }
};
