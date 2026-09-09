<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('width_cm', 12, 4)->nullable()->after('unit');
            $table->decimal('length_cm', 12, 4)->nullable()->after('width_cm');
        });

        Schema::table('product_recipe_items', function (Blueprint $table) {
            $table->string('name')->nullable()->after('product_recipe_id');
            $table->string('component_key')->nullable()->after('name');
            $table->string('component_type')->default('MATERIAL')->after('component_key');
            $table->string('selectable_material_group')->nullable()->after('material_id');
            $table->string('unit')->nullable()->after('quantity');
            $table->string('calculation_basis')->default('FIXED')->after('unit');
            $table->boolean('is_primary_material')->default(false)->after('calculation_basis');
            $table->decimal('wastage_percentage', 7, 4)->nullable()->after('is_primary_material');
            $table->boolean('is_active')->default(true)->after('wastage_percentage');
        });

        foreach (DB::table('product_recipe_items')->orderBy('id')->get() as $component) {
            $material = DB::table('materials')->find($component->material_id);
            $recipe = DB::table('product_recipes')->find($component->product_recipe_id);
            $product = $recipe ? DB::table('products')->find($recipe->product_id) : null;
            DB::table('product_recipe_items')->where('id', $component->id)->update([
                'name' => $material?->name,
                'component_key' => 'material-'.$component->material_id,
                'unit' => $material?->unit,
                'is_primary_material' => $product && (int) $product->default_material_id === (int) $component->material_id,
            ]);
        }

        Schema::table('special_effects', function (Blueprint $table) {
            $table->string('percentage_basis')->nullable()->after('cost_type');
            $table->date('effective_from')->nullable()->after('selling_price');
            $table->date('effective_to')->nullable()->after('effective_from');
        });

        Schema::create('product_special_effect', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('special_effect_id')->constrained()->cascadeOnDelete();
            $table->unique(['product_id', 'special_effect_id'], 'pse_unique');
        });

        Schema::create('product_category_special_effect', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('special_effect_id')->constrained()->cascadeOnDelete();
            $table->unique(['product_category_id', 'special_effect_id'], 'pcse_unique');
        });

        Schema::table('addons', function (Blueprint $table) {
            $table->string('percentage_basis')->nullable()->after('cost_type');
            $table->date('effective_from')->nullable()->after('cost');
            $table->date('effective_to')->nullable()->after('effective_from');
        });

        Schema::create('addon_print_method', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_method_id')->constrained()->cascadeOnDelete();
            $table->unique(['addon_id', 'print_method_id'], 'apm_unique');
        });

        Schema::create('addon_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unique(['addon_id', 'product_id'], 'ap_unique');
        });

        Schema::create('addon_product_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->unique(['addon_id', 'product_category_id'], 'apc_unique');
        });

        Schema::table('overhead_rates', function (Blueprint $table) {
            $table->string('percentage_basis')->nullable()->after('cost_type');
        });

        Schema::table('wastage_rules', function (Blueprint $table) {
            $table->string('percentage_basis')->nullable()->after('cost_type');
        });

        Schema::table('print_location_rates', function (Blueprint $table) {
            $table->string('percentage_basis')->nullable()->after('cost_type');
        });

        Schema::table('size_surcharges', function (Blueprint $table) {
            $table->string('percentage_basis')->nullable()->after('cost_type');
        });

        Schema::table('quote_print_locations', function (Blueprint $table) {
            $table->foreignId('print_method_id')->nullable()->after('print_location_id')->constrained()->nullOnDelete();
        });

        Schema::table('artworks', function (Blueprint $table) {
            $table->foreignId('quote_print_location_id')->nullable()->after('print_location_id')->constrained('quote_print_locations')->nullOnDelete();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1)->after('number');
            $table->foreignId('parent_quote_id')->nullable()->after('revision')->constrained('quotes')->nullOnDelete();
        });

        Schema::create('discount_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('discount_type')->default('percentage');
            $table->decimal('requested_value', 7, 4);
            $table->decimal('original_total', 12, 2);
            $table->decimal('requested_total', 12, 2);
            $table->decimal('original_margin', 7, 4);
            $table->decimal('projected_margin', 7, 4);
            $table->text('reason');
            $table->string('status')->default('PENDING');
            $table->decimal('approved_value', 7, 4)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        DB::table('special_effects')->where('cost_type', 'per_location')->update(['cost_type' => 'per_location_per_job']);
        DB::table('addons')->where('cost_type', 'per_location')->update(['cost_type' => 'per_location_per_piece']);
        DB::table('labor_rates')->where('rate_type', 'per_location')->update(['rate_type' => 'per_location_per_piece']);
    }

    public function down(): void
    {
        DB::table('labor_rates')->where('rate_type', 'per_location_per_piece')->update(['rate_type' => 'per_location']);
        DB::table('addons')->where('cost_type', 'per_location_per_piece')->update(['cost_type' => 'per_location']);
        DB::table('special_effects')->where('cost_type', 'per_location_per_job')->update(['cost_type' => 'per_location']);

        Schema::dropIfExists('discount_requests');

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_quote_id');
            $table->dropColumn('revision');
        });

        Schema::table('artworks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quote_print_location_id');
        });

        Schema::table('quote_print_locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('print_method_id');
        });

        Schema::table('size_surcharges', fn (Blueprint $table) => $table->dropColumn('percentage_basis'));
        Schema::table('print_location_rates', fn (Blueprint $table) => $table->dropColumn('percentage_basis'));
        Schema::table('wastage_rules', fn (Blueprint $table) => $table->dropColumn('percentage_basis'));
        Schema::table('overhead_rates', fn (Blueprint $table) => $table->dropColumn('percentage_basis'));

        Schema::dropIfExists('addon_product_category');
        Schema::dropIfExists('addon_product');
        Schema::dropIfExists('addon_print_method');
        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn(['percentage_basis', 'effective_from', 'effective_to']);
        });

        Schema::dropIfExists('product_category_special_effect');
        Schema::dropIfExists('product_special_effect');
        Schema::table('special_effects', function (Blueprint $table) {
            $table->dropColumn(['percentage_basis', 'effective_from', 'effective_to']);
        });

        Schema::table('product_recipe_items', function (Blueprint $table) {
            $table->dropColumn(['name', 'component_key', 'component_type', 'selectable_material_group', 'unit', 'calculation_basis', 'is_primary_material', 'wastage_percentage', 'is_active']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['width_cm', 'length_cm']);
        });
    }
};
