<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner codes (app brief item 3). Admin-created codes; a customer is
 * tagged with at most one, set at sign-up or later from the profile and
 * never changed by the customer. Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_codes')) {
            Schema::create('partner_codes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique(); // stored uppercase
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'partner_code_id')) {
                $table->unsignedBigInteger('partner_code_id')->nullable()->index();
            }
            if (! Schema::hasColumn('customers', 'partner_code_set_at')) {
                $table->timestamp('partner_code_set_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            foreach (['partner_code_id', 'partner_code_set_at'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    if ($column === 'partner_code_id') {
                        $table->dropIndex(['partner_code_id']);
                    }
                    $table->dropColumn($column);
                }
            }
        });
        Schema::dropIfExists('partner_codes');
    }
};
