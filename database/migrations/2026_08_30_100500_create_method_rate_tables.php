<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Method-specific rate tables.
 *
 * Every table carries effective_from / effective_to so a quote can resolve the
 * rates that applied on its own calculation date. No calculator ever falls back
 * to a hard-coded number: a missing row is an error, never zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- DTF / digital transfer -------------------------------------
        Schema::create('dtf_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Default DTF rate');
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('cost_per_cm2', 12, 6);            // film + ink per cm2
            $table->decimal('pressing_cost_per_location', 12, 4);
            $table->decimal('setup_cost', 12, 4)->default(0);
            $table->decimal('minimum_area_cm2', 12, 4)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'effective_from']);
        });

        // ---- Embroidery --------------------------------------------------
        Schema::create('embroidery_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Default embroidery rate');
            $table->decimal('digitizing_fee', 12, 4);          // once per design
            $table->decimal('cost_per_color', 12, 4);
            $table->decimal('setup_cost_per_location', 12, 4)->default(0);
            $table->unsignedInteger('minimum_stitches')->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'effective_from']);
        });

        Schema::create('embroidery_stitch_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('embroidery_rate_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_stitches');
            $table->unsignedInteger('max_stitches')->nullable(); // NULL = open ended
            $table->decimal('cost_per_thousand_stitches', 12, 4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['embroidery_rate_id', 'min_stitches'], 'est_rate_min_idx');
        });

        // ---- Silkscreen ---------------------------------------------------
        Schema::create('silkscreen_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Default silkscreen rate');
            $table->decimal('screen_setup_cost_per_color', 12, 4);
            $table->decimal('film_cost_per_color', 12, 4);
            $table->decimal('ink_cost_per_color_per_piece', 12, 4);
            $table->decimal('labor_cost_per_color_per_piece', 12, 4);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'effective_from']);
        });

        // Print-size bands (small / medium / large / jumbo) and their multipliers.
        Schema::create('silkscreen_size_multipliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('silkscreen_rate_id')->constrained()->cascadeOnDelete();
            $table->string('size_code');   // SMALL, MEDIUM, LARGE, JUMBO
            $table->string('label');
            $table->decimal('multiplier', 9, 4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['silkscreen_rate_id', 'size_code'], 'ssm_unique');
        });

        // ---- Sublimation ---------------------------------------------------
        // Fabric consumption comes from the product recipe; this covers the
        // transfer printing side only.
        Schema::create('sublimation_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Default sublimation rate');
            $table->decimal('paper_cost_per_sqm', 12, 6);
            $table->decimal('ink_cost_per_sqm', 12, 6);
            $table->decimal('printing_cost_per_sqm', 12, 6);
            $table->decimal('pressing_cost_per_piece', 12, 4);
            $table->decimal('setup_cost', 12, 4)->default(0);
            $table->decimal('paper_waste_percentage', 7, 4)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'effective_from']);
        });

        // ---- Stickers / decals ----------------------------------------------
        Schema::create('sticker_print_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();  // CUT_OUT, UV_PRINT, ECO_SOLVENT, UV_DTF
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sticker_laminates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();  // NONE, MATTE, GLOSSY
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sticker_cut_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();  // SQUARE, DIE_CUT, KISS_CUT, CONTOUR
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Grain: one row per (print type, material, laminate) combination.
        Schema::create('sticker_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sticker_print_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('sticker_laminate_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('printing_cost_per_cm2', 12, 6);
            $table->decimal('lamination_cost_per_cm2', 12, 6)->default(0);
            $table->decimal('setup_cost', 12, 4)->default(0);
            $table->decimal('minimum_area_cm2', 12, 4)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['sticker_print_type_id', 'material_id', 'is_active'], 'sr_lookup_idx');
        });

        // Cutting is priced separately because it depends on the cut type.
        Schema::create('sticker_cut_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sticker_cut_type_id')->constrained()->cascadeOnDelete();
            $table->string('cost_type')->default('per_piece'); // per_piece|per_cm2|per_job
            $table->decimal('amount', 12, 6);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ---- Caps --------------------------------------------------------
        Schema::create('cap_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();  // TRUCKER, SNAPBACK, DAD_CAP, BUCKET
            $table->string('name');
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Grain: one row per (cap type, decoration method).
        Schema::create('cap_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cap_type_id')->constrained()->cascadeOnDelete();
            // The decoration technique applied to the cap (EMBROIDERY, DTF, PATCH, ...).
            $table->foreignId('print_method_id')->constrained()->cascadeOnDelete();
            $table->decimal('blank_cost_per_piece', 12, 4);
            $table->decimal('decoration_cost_per_piece', 12, 4);
            $table->decimal('setup_cost', 12, 4)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['cap_type_id', 'print_method_id', 'effective_from'], 'cr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cap_rates');
        Schema::dropIfExists('cap_types');
        Schema::dropIfExists('sticker_cut_rates');
        Schema::dropIfExists('sticker_rates');
        Schema::dropIfExists('sticker_cut_types');
        Schema::dropIfExists('sticker_laminates');
        Schema::dropIfExists('sticker_print_types');
        Schema::dropIfExists('sublimation_rates');
        Schema::dropIfExists('silkscreen_size_multipliers');
        Schema::dropIfExists('silkscreen_rates');
        Schema::dropIfExists('embroidery_stitch_tiers');
        Schema::dropIfExists('embroidery_rates');
        Schema::dropIfExists('dtf_rates');
    }
};
