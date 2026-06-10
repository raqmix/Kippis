<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_requests', function (Blueprint $table) {
            $table->string('reference_number', 20)
                ->nullable()
                ->unique()
                ->after('id');
        });

        // Backfill existing rows so the column can never be null going
        // forward (and so customers calling back about pre-existing
        // requests can be looked up by ref).
        DB::table('event_requests')
            ->whereNull('reference_number')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('event_requests')
                    ->where('id', $row->id)
                    ->update([
                        'reference_number' => 'EVT-' . strtoupper(Str::random(6)),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('event_requests', function (Blueprint $table) {
            $table->dropUnique(['reference_number']);
            $table->dropColumn('reference_number');
        });
    }
};
