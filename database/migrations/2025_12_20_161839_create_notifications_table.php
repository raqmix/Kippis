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
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
            $table->string('type'); // system, admin, ticket, security
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data like ticket_id, etc.
            $table->timestamp('read_at')->nullable();
            $table->string('action_url')->nullable(); // URL to navigate when clicked
            $table->string('action_text')->nullable(); // Text for action button
            $table->timestamps();
            
            $table->index(['admin_id', 'read_at']);
            $table->index(['admin_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
