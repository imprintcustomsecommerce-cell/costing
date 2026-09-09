<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DTF area-based size multipliers, plus a separate rate for pressing the
 * second and subsequent locations on the same garment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtf_rates', function (Blueprint $table) {
            // NULL means "same as pressing_cost_per_location for every location",
            // which is the behaviour that applied before this column existed.
            $table->decimal('pressing_cost_additional_location', 12, 4)
                ->nullable()
                ->after('pressing_cost_per_location');
        });

        // Larger transfers cost more per cm2 to press and handle. Bands are
        // matched on the print area of a single location.
        Schema::create('dtf_size_multipliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dtf_rate_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_area_cm2', 12, 4);
            $table->decimal('max_area_cm2', 12, 4)->nullable(); // NULL = open ended
            $table->decimal('multiplier', 9, 4)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['dtf_rate_id', 'min_area_cm2'], 'dsm_rate_min_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtf_size_multipliers');
        Schema::table('dtf_rates', function (Blueprint $table) {
            $table->dropColumn('pressing_cost_additional_location');
        });
    }
};
