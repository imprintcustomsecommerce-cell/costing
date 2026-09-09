<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The printed size a quotation line was costed at.
 *
 * Area-measured materials — transfer film, vinyl — are consumed by how big the
 * artwork is, so the size is part of what the line was priced on and belongs on
 * the record beside the price it produced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->decimal('artwork_width_cm', 10, 2)->nullable()->after('quantity');
            $table->decimal('artwork_height_cm', 10, 2)->nullable()->after('artwork_width_cm');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['artwork_width_cm', 'artwork_height_cm']);
        });
    }
};
