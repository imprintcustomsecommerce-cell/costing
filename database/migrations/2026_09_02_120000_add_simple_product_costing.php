<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simple product costing: a product is a list of materials and quantities, and
 * its cost is the sum of them.
 *
 * Materials already carry one price. That becomes the bulk price, and a retail
 * price sits beside it, so a product can be costed both ways from the same
 * recipe — bulk for volume orders, retail for one-offs.
 *
 * Additive. Existing quantities default to 1, which is what the pivot meant
 * before it could hold a number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('retail_cost', 14, 4)->nullable()->after('current_cost');
        });

        Schema::table('material_product', function (Blueprint $table) {
            $table->decimal('quantity', 14, 4)->default(1)->after('material_id');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('retail_cost');
        });

        Schema::table('material_product', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
