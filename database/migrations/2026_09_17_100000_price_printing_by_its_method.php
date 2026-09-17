<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Printing is not one number: each method is quoted the way its work is
     * actually bought.
     *
     * Silkscreen is a screen per design, so it is a base that covers the first
     * design and a smaller charge for each one after it - 80 + 10 + 10 for
     * three.
     *
     * Embroidery is bought by the stitch, so it is a base plus a rate per
     * stitch. The rate is a fraction of a centavo, which is why it is held to
     * six decimal places: rounded to two it would be nothing at all.
     *
     * Sublimation, DTF, eco solvent and vinyl stay a single price for the
     * piece.
     *
     * printing_cost keeps its meaning throughout as the base, so nothing that
     * is already priced moves: a product with no design or stitch figures
     * costs exactly what it costs today.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Silkscreen: the first design is in the base, the rest are extra.
            $table->unsignedInteger('design_count')->default(1)->after('printing_cost');
            $table->decimal('extra_design_cost', 14, 4)->default(0)->after('design_count');

            // Embroidery: how big the logo is, and what a stitch costs.
            $table->unsignedInteger('stitch_count')->default(0)->after('extra_design_cost');
            $table->decimal('cost_per_stitch', 12, 6)->default(0)->after('stitch_count');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['design_count', 'extra_design_cost', 'stitch_count', 'cost_per_stitch']);
        });
    }
};
