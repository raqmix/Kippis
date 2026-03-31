<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foodics_modifier_options', function (Blueprint $table) {
            $table->id();
            $table->string('foodics_id')->unique();
            $table->foreignId('foodics_modifier_group_id')->constrained('foodics_modifier_groups')->onDelete('cascade');
            $table->json('name_json');
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('foodics_modifier_group_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foodics_modifier_options');
    }
};
