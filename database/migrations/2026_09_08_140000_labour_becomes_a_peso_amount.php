<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Labour is entered as money, not as time.
     *
     * Minutes asked for a figure nobody actually holds - a shop knows that
     * pressing a shirt costs about fifteen pesos long before it knows it takes
     * four minutes - and the hourly rate behind them was a second number to
     * keep true. A peso amount per piece says the same thing in the terms the
     * work is already thought about in.
     *
     * Existing minutes are converted at the rate that was in force, so nothing
     * that was already priced changes.
     */
    public function up(): void
    {
        $rate = (float) (DB::table('settings')->where('key', 'labour_rate_per_hour')->value('value') ?? 0);

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('labour_cost', 12, 2)->nullable()->after('description');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->decimal('labour_cost', 12, 2)->nullable()->after('quantity');
        });

        if ($rate > 0) {
            DB::statement('update products set labour_cost = round(labour_minutes / 60 * ?, 2) where labour_minutes is not null', [$rate]);
            DB::statement('update quotation_items set labour_cost = round(labour_minutes / 60 * ?, 2) where labour_minutes is not null', [$rate]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('labour_minutes');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn('labour_minutes');
        });

        // The hourly rate has nothing left to price.
        DB::table('settings')->where('key', 'labour_rate_per_hour')->delete();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('labour_minutes', 10, 2)->nullable()->after('description');
            $table->dropColumn('labour_cost');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->decimal('labour_minutes', 10, 2)->nullable()->after('quantity');
            $table->dropColumn('labour_cost');
        });
    }
};
