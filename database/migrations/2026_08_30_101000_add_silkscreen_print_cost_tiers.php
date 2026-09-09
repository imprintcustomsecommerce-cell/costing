<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Silkscreen print cost as a quantity x colour matrix.
 *
 * The trade prices the first colour higher than each additional colour, and
 * both fall as quantity rises. The existing flat per-colour columns are kept so
 * a rate row can choose either model via silkscreen_rates.pricing_model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('silkscreen_rates', function (Blueprint $table) {
            // flat_per_color = original behaviour (ink + labor per colour per piece).
            // quantity_matrix = use silkscreen_print_cost_tiers below.
            $table->string('pricing_model', 24)->default('flat_per_color')->after('name');
        });

        Schema::create('silkscreen_print_cost_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('silkscreen_rate_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_quantity');
            $table->unsignedInteger('max_quantity')->nullable(); // NULL = open ended
            $table->decimal('first_color_cost', 12, 4);
            $table->decimal('additional_color_cost', 12, 4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['silkscreen_rate_id', 'min_quantity'], 'spct_rate_min_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('silkscreen_print_cost_tiers');
        Schema::table('silkscreen_rates', function (Blueprint $table) {
            $table->dropColumn('pricing_model');
        });
    }
};
