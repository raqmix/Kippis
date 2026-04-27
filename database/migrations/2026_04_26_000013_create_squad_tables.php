<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('squad_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('invite_code', 8)->unique()->index();
            $table->enum('status', ['open', 'locked', 'checked_out', 'cancelled'])->default('open')->index();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('squad_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('squad_session_id')->constrained('squad_sessions')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('nickname', 50)->nullable();
            $table->timestamp('joined_at');
            $table->boolean('is_host')->default(false);
            $table->timestamps();

            $table->unique(['squad_session_id', 'customer_id']);
        });

        Schema::create('squad_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('squad_session_id')->constrained('squad_sessions')->cascadeOnDelete();
            $table->foreignId('squad_member_id')->constrained('squad_members')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->enum('product_kind', ['standard', 'mix'])->default('standard');
            $table->unsignedInteger('quantity')->default(1);
            $table->json('modifiers')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('unit_price');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('squad_cart_items');
        Schema::dropIfExists('squad_members');
        Schema::dropIfExists('squad_sessions');
    }
};
