<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foodics_modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->string('foodics_id')->unique();
            $table->json('name_json');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foodics_modifier_groups');
    }
};
