<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtf_rates', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('material_id');
        });

        Schema::table('print_location_rates', function (Blueprint $table) {
            $table->string('operation')->default('supplemental_location')->after('print_location_id');
        });

        $defaultDtfRate = DB::table('dtf_rates')
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->value('id');
        if ($defaultDtfRate) {
            DB::table('dtf_rates')->where('id', $defaultDtfRate)->update(['is_default' => true]);
        }

        Schema::create('material_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('material_group_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['material_group_id', 'material_id'], 'mgm_group_material_unique');
        });

        Schema::table('product_recipe_items', function (Blueprint $table) {
            $table->foreignId('selectable_material_group_id')
                ->nullable()
                ->after('selectable_material_group')
                ->constrained('material_groups')
                ->nullOnDelete();
        });

        foreach (DB::table('product_recipe_items')
            ->whereNotNull('selectable_material_group')
            ->where('selectable_material_group', '<>', '')
            ->orderBy('id')
            ->get() as $component) {
            $code = strtolower(trim((string) $component->selectable_material_group));
            $group = DB::table('material_groups')->where('code', $code)->first();
            if (! $group) {
                $groupId = DB::table('material_groups')->insertGetId([
                    'code' => $code,
                    'name' => ucwords(str_replace(['_', '-'], ' ', $code)),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $groupId = $group->id;
            }

            DB::table('product_recipe_items')->where('id', $component->id)
                ->update(['selectable_material_group_id' => $groupId]);

            if ($component->material_id) {
                DB::table('material_group_material')->insertOrIgnore([
                    'material_group_id' => $groupId,
                    'material_id' => $component->material_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Legacy recipes used the product's supported-material list as the
            // only allow-list. Carry those existing choices into the new group
            // so an incremental migration does not invalidate staff options.
            $productId = DB::table('product_recipes')
                ->where('id', $component->product_recipe_id)
                ->value('product_id');
            if ($productId) {
                foreach (DB::table('material_product')->where('product_id', $productId)->pluck('material_id') as $materialId) {
                    DB::table('material_group_material')->insertOrIgnore([
                        'material_group_id' => $groupId,
                        'material_id' => $materialId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Preserve old values for forensic/reference purposes while removing the
        // misleading fields from the live pricing API.
        Schema::table('special_effects', function (Blueprint $table) {
            $table->renameColumn('selling_price', 'legacy_selling_price');
        });
        Schema::table('cap_rates', function (Blueprint $table) {
            $table->renameColumn('blank_cost_per_piece', 'legacy_blank_cost_per_piece');
        });
    }

    public function down(): void
    {
        Schema::table('cap_rates', function (Blueprint $table) {
            $table->renameColumn('legacy_blank_cost_per_piece', 'blank_cost_per_piece');
        });
        Schema::table('special_effects', function (Blueprint $table) {
            $table->renameColumn('legacy_selling_price', 'selling_price');
        });

        Schema::table('product_recipe_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selectable_material_group_id');
        });
        Schema::dropIfExists('material_group_material');
        Schema::dropIfExists('material_groups');

        Schema::table('dtf_rates', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
        Schema::table('print_location_rates', function (Blueprint $table) {
            $table->dropColumn('operation');
        });
    }
};
