<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // Make product_id nullable (for mix/creator_mix items)
            $table->foreignId('product_id')->nullable()->change();
            
            // Add unified fields
            $table->enum('item_type', ['product', 'mix', 'creator_mix'])->default('product')->after('product_id');
            $table->unsignedBigInteger('ref_id')->nullable()->after('item_type');
            $table->string('name')->nullable()->after('ref_id');
            $table->json('configuration')->nullable()->after('name');
            
            // Add indexes
            $table->index('item_type');
            $table->index('ref_id');
        });

        // Migrate existing data: populate unified fields from existing product_id
        $cartItems = DB::table('cart_items')
            ->whereNotNull('product_id')
            ->join('products', 'cart_items.product_id', '=', 'products.id')
            ->select('cart_items.id', 'cart_items.product_id', 'products.name_json')
            ->get();

        foreach ($cartItems as $item) {
            $nameJson = is_string($item->name_json) ? json_decode($item->name_json, true) : $item->name_json;
            $name = $nameJson['en'] ?? $nameJson['ar'] ?? 'Product';
            
            DB::table('cart_items')
                ->where('id', $item->id)
                ->update([
                    'item_type' => 'product',
                    'ref_id' => $item->product_id,
                    'name' => $name,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // Remove indexes
            $table->dropIndex(['item_type']);
            $table->dropIndex(['ref_id']);
            
            // Remove unified fields
            $table->dropColumn(['item_type', 'ref_id', 'name', 'configuration']);
            
            // Make product_id required again (this may fail if there are null values)
            $table->foreignId('product_id')->nullable(false)->change();
        });
    }
};
