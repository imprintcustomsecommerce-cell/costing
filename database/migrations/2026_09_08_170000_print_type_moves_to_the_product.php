<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The route through the floor belongs to the product, not the quotation.
     *
     * A product already says how it is made: one carrying DTF film, DTF ink and
     * adhesive powder is a DTF job, and nothing about quoting it changes that.
     * Asking again on every line was work for the person quoting and a way for
     * the answer to contradict the materials.
     *
     * The line keeps its own copy, snapshotted at the price it was quoted at,
     * so re-routing a product later does not rewrite quotations already given.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('print_type')->nullable()->after('description');
        });

        // Anything already quoted tells us how that product is made. Written
        // row by row rather than as a joined update, which not every database
        // this runs on will parse.
        DB::table('quotation_items')
            ->whereNotNull('print_type')
            ->groupBy('product_id')
            ->select('product_id', DB::raw('max(print_type) as print_type'))
            ->get()
            ->each(fn ($row) => DB::table('products')
                ->where('id', $row->product_id)
                ->update(['print_type' => $row->print_type]));
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('print_type');
        });
    }
};
