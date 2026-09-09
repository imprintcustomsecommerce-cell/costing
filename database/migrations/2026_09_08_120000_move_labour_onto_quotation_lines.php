<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Labour belongs to the job, not to the catalogue.
     *
     * How long a piece takes varies with the order in front of you - a rushed
     * run, an awkward placement, a first-timer on the press - so the minutes
     * are entered while quoting and snapshotted onto the line, alongside the
     * cost they produced.
     *
     * The product keeps its own minutes as the usual figure, which pre-fills a
     * new line so nobody retypes it, but the quotation is what decides the
     * price.
     */
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->decimal('labour_minutes', 10, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn('labour_minutes');
        });
    }
};
