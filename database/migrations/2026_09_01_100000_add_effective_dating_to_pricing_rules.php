<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Effective dating for the pricing rules that still lacked it.
 *
 * Without this, updating the rate for an existing operation meant leaving two
 * active rows behind, and the engine summed both: a "Finishing" rate raised
 * from 20 to 25 charged 45. Rates are now versioned by effective period, so a
 * replacement closes the old period instead of stacking on top of it.
 *
 * Purely additive. Existing rows are backfilled with an open-ended period
 * starting at their creation date, so current pricing behaviour is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labor_rates', function (Blueprint $table) {
            $table->date('effective_from')->nullable()->after('rate');
            $table->date('effective_to')->nullable()->after('effective_from');
            // Labor may be scoped to a category, not just one product.
            $table->foreignId('product_category_id')->nullable()->after('product_id')
                ->constrained()->nullOnDelete();
            // Brings labor into line with every other charge-bearing table.
            $table->string('cost_type')->nullable()->after('rate_type');
            $table->string('percentage_basis')->nullable()->after('cost_type');
        });

        Schema::table('size_surcharges', function (Blueprint $table) {
            $table->date('effective_from')->nullable()->after('amount');
            $table->date('effective_to')->nullable()->after('effective_from');
        });

        Schema::table('print_location_rates', function (Blueprint $table) {
            $table->date('effective_from')->nullable()->after('additional_location_amount');
            $table->date('effective_to')->nullable()->after('effective_from');
        });

        // Backfill: every existing rule starts on the day it was created and has
        // no end, so nothing that priced correctly yesterday changes today.
        foreach (['labor_rates', 'size_surcharges', 'print_location_rates'] as $table) {
            DB::table($table)->whereNull('effective_from')->update([
                'effective_from' => DB::raw('date(created_at)'),
            ]);
        }

        // `rate_type` already carries the charge shape for labor; mirror it into
        // cost_type so ChargeCalculator can be driven from one column later.
        DB::table('labor_rates')->whereNull('cost_type')->update([
            'cost_type' => DB::raw('rate_type'),
        ]);
    }

    public function down(): void
    {
        Schema::table('print_location_rates', function (Blueprint $table) {
            $table->dropColumn(['effective_from', 'effective_to']);
        });

        Schema::table('size_surcharges', function (Blueprint $table) {
            $table->dropColumn(['effective_from', 'effective_to']);
        });

        Schema::table('labor_rates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_category_id');
            $table->dropColumn(['effective_from', 'effective_to', 'cost_type', 'percentage_basis']);
        });
    }
};
