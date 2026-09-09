<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two gaps in the simple costing model.
     *
     * Consumables like DTF ink and adhesive powder are bought by the gram but
     * consumed by the size of the print. Without a coverage rate they were
     * costed as a flat gram per garment, so a 30x30cm print was charged the
     * same ink as a 5x5cm one. A material that names how much of it one square
     * metre of print consumes is costed by area from now on.
     *
     * Nothing else changes: a material that leaves this null keeps being
     * counted, weighed or measured by length exactly as before.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('coverage_per_sqm', 12, 4)->nullable()->after('length_cm');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('coverage_per_sqm');
        });
    }
};
