<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Overhead and wastage rules are summed by the engine, exactly like labor, so
 * they carried the same replacement hazard: raising "Shop overhead" from 12% to
 * 15% by adding a second active row charged 27%. Effective dating lets a new
 * version close the previous period instead of stacking on it.
 *
 * Additive; existing rows are backfilled with an open period from their
 * creation date, so nothing that priced correctly before changes now.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['overhead_rates', 'wastage_rules'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->date('effective_from')->nullable()->after('value');
                $blueprint->date('effective_to')->nullable()->after('effective_from');
            });

            DB::table($table)->whereNull('effective_from')->update([
                'effective_from' => DB::raw('date(created_at)'),
            ]);
        }
    }

    public function down(): void
    {
        foreach (['overhead_rates', 'wastage_rules'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['effective_from', 'effective_to']);
            });
        }
    }
};
