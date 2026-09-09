<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Margin rules and quantity tiers gain effective dating so they can be replaced
 * the same way every other pricing rule is.
 *
 * Quantity tiers already refuse overlapping ranges, which left no way to change
 * an existing band at all: the replacement always collided with the original.
 * Closing the old period is that missing path.
 *
 * Additive; existing rows are backfilled with an open period from their creation
 * date, so current selection behaviour does not change.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['margin_rules', 'quantity_tiers'] as $table) {
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
        foreach (['margin_rules', 'quantity_tiers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['effective_from', 'effective_to']);
            });
        }
    }
};
