<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the quotation system and the pricing engine.
 *
 * The application is now a product costing tool: a product is a list of
 * materials and quantities, and its cost is the sum of them. Everything below
 * belonged to the quoting and print-pricing machinery that replaced.
 *
 * Not reversible. The data was backed up to costing-DB-BACKUP-before-strip.sql
 * before this ran; restoring that dump is the way back.
 */
return new class extends Migration
{
    /**
     * Children first: a table is dropped before anything it points at, so the
     * foreign keys never dangle mid-migration.
     */
    private const TABLES = [
        // Quotations and everything hanging off one.
        'quote_addons', 'quote_effects', 'quote_sizes', 'quote_print_locations',
        'quote_calculations', 'quote_items', 'discount_requests', 'artworks', 'quotes',
        'customers', 'customer_types',

        // Optional charges and their applicability pivots.
        'addon_print_method', 'addon_product', 'addon_product_category', 'addons',
        'print_method_special_effect', 'product_special_effect',
        'product_category_special_effect', 'special_effects',

        // Per-method rates and their matrices.
        'dtf_rate_media_components', 'dtf_size_multipliers', 'dtf_rates',
        'embroidery_stitch_tiers', 'embroidery_rates',
        'silkscreen_print_cost_tiers', 'silkscreen_size_multipliers', 'silkscreen_rates',
        'sublimation_rates', 'sticker_cut_rates', 'sticker_rates',
        'sticker_cut_types', 'sticker_laminates', 'sticker_print_types',
        'cap_rates', 'cap_types', 'method_rate_material_components',

        // Pricing rules.
        'margin_rules', 'quantity_tiers', 'overhead_rates', 'wastage_rules',
        'size_surcharges', 'rounding_rules', 'labor_rates',
        'print_location_rates', 'pricing_versions',

        // Print methods and locations.
        'print_method_product', 'print_locations', 'print_methods',

        // The old bill of materials, replaced by material_product.quantity.
        'product_recipe_items', 'product_recipes', 'product_sizes',
        'material_group_material', 'material_groups',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        // products.default_material_id meant "the blank this product is built
        // from". The recipe now says that, per material, with a quantity.
        if (Schema::hasColumn('products', 'default_material_id')) {
            Schema::table('products', function ($table) {
                $table->dropForeign(['default_material_id']);
                $table->dropColumn('default_material_id');
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Irreversible. Restore costing-DB-BACKUP-before-strip.sql to recover the quotation and pricing tables.'
        );
    }
};
