<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DTF / EMBROIDERY / SILKSCREEN / SUBLIMATION / STICKER / CAP.
        // `calculator` maps to a PricingCalculator implementation.
        Schema::create('print_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('calculator');
            $table->text('description')->nullable();
            // NULL means "no minimum configured", which is a valid business state.
            // It is never used as a substitute for a missing rate.
            $table->decimal('minimum_charge_per_piece', 12, 2)->nullable();
            $table->decimal('minimum_job_charge', 12, 2)->nullable();
            $table->boolean('requires_artwork')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('print_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();  // FRONT, BACK, LEFT_CHEST, ...
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Per-method charge for printing at a given location.
        Schema::create('print_location_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_location_id')->constrained()->cascadeOnDelete();
            $table->string('cost_type')->default('per_piece'); // per_piece|per_job|percentage
            $table->decimal('amount', 12, 4)->default(0);
            // Applied instead of `amount` for every location beyond the first.
            $table->decimal('additional_location_amount', 12, 4)->nullable();
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['print_method_id', 'print_location_id'], 'plr_method_location_unique');
        });

        // Quantity economics differ per print method, so tiers are method-scoped.
        Schema::create('quantity_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_method_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_quantity');
            $table->unsignedInteger('max_quantity')->nullable(); // NULL = open ended
            // multiplier: printing cost x value. percentage: adjust printing cost by value%.
            $table->string('adjustment_type')->default('multiplier');
            $table->decimal('value', 9, 4)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['print_method_id', 'min_quantity', 'max_quantity'], 'qt_method_range_idx');
        });

        // Size surcharges, scoped globally, per product category, or per product.
        Schema::create('size_surcharges', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->default('global'); // global|product_category|product
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('size_code');                // XS, S, M, L, XL, 2XL, ...
            $table->string('cost_type')->default('per_piece'); // per_piece|percentage
            $table->decimal('amount', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['scope', 'scope_id', 'size_code'], 'ss_scope_size_idx');
        });

        Schema::create('special_effects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // per_piece|per_location|per_color|per_cm2|percentage|flat_fee
            $table->string('cost_type');
            $table->decimal('cost', 12, 4)->default(0);
            $table->decimal('selling_price', 12, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('print_method_special_effect', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('special_effect_id')->constrained()->cascadeOnDelete();
            // Optional per-method override of the effect base cost.
            $table->decimal('cost_override', 12, 4)->nullable();
            $table->timestamps();

            $table->unique(['print_method_id', 'special_effect_id'], 'pmse_unique');
        });

        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('cost_type'); // per_piece|per_location|per_job|percentage|flat_fee
            $table->decimal('cost', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('labor_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // cutting|sewing|printing|pressing|embroidery|packing|quality_control|finishing
            $table->string('operation');
            $table->string('rate_type'); // hourly|per_piece|per_location|per_job
            $table->decimal('rate', 12, 4);
            // Optional narrowing. NULL = applies to every method / product.
            $table->foreignId('print_method_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            // Only used when rate_type = hourly.
            $table->decimal('minutes_per_unit', 9, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['operation', 'is_active']);
        });

        Schema::create('overhead_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cost_type'); // percentage|flat|per_item|per_job
            $table->decimal('value', 12, 4);
            $table->foreignId('print_method_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Resolved by priority: most specific matching rule wins.
        Schema::create('margin_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('print_method_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('pricing_model')->default('margin'); // margin|markup
            $table->decimal('value', 7, 4); // 45.0000 = 45%
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['print_method_id', 'customer_type_id', 'is_active'], 'mr_scope_idx');
        });

        // Wastage that is not tied to a specific material.
        Schema::create('wastage_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cost_type'); // percentage|flat
            $table->decimal('value', 9, 4);
            $table->foreignId('print_method_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wastage_rules');
        Schema::dropIfExists('margin_rules');
        Schema::dropIfExists('overhead_rates');
        Schema::dropIfExists('labor_rates');
        Schema::dropIfExists('addons');
        Schema::dropIfExists('print_method_special_effect');
        Schema::dropIfExists('special_effects');
        Schema::dropIfExists('size_surcharges');
        Schema::dropIfExists('quantity_tiers');
        Schema::dropIfExists('print_location_rates');
        Schema::dropIfExists('print_locations');
        Schema::dropIfExists('print_methods');
    }
};
