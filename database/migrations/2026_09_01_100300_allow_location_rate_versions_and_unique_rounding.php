<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two governance fixes.
 *
 * 1. print_location_rates carried UNIQUE(print_method_id, print_location_id),
 *    which made a second effective-dated version impossible: the only way to
 *    change a location charge was to overwrite it, destroying the history that
 *    lets an old quote be explained. Uniqueness moves to include the start date.
 *
 * 2. Only one global rounding rule may be active at a time. The engine takes the
 *    first active global rule, so several would make the selected rule depend on
 *    row order. Older duplicates are deactivated, newest kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The replacement is created first: MariaDB refuses to drop an index a
        // foreign key depends on, and the new index leads with the same column
        // so it can take over that role before the old one goes.
        Schema::table('print_location_rates', function (Blueprint $table) {
            $table->unique(
                ['print_method_id', 'print_location_id', 'operation', 'effective_from'],
                'plr_method_location_version_unique'
            );
        });

        Schema::table('print_location_rates', function (Blueprint $table) {
            $table->dropUnique('plr_method_location_unique');
        });

        // Keep the newest active global rule; retire the rest so selection is
        // deterministic rather than dependent on row order.
        $keep = DB::table('rounding_rules')
            ->where('scope', 'global')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->value('id');

        if ($keep) {
            DB::table('rounding_rules')
                ->where('scope', 'global')
                ->where('is_active', true)
                ->where('id', '!=', $keep)
                ->update(['is_active' => false]);
        }
    }

    public function down(): void
    {
        Schema::table('print_location_rates', function (Blueprint $table) {
            $table->unique(['print_method_id', 'print_location_id'], 'plr_method_location_unique');
        });

        Schema::table('print_location_rates', function (Blueprint $table) {
            $table->dropUnique('plr_method_location_version_unique');
        });
    }
};
