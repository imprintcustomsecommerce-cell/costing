<?php

use App\Support\ProductionPipeline;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product costing now mirrors the way a garment is actually built:
     * materials + printing + labour + packaging.
     *
     * Existing products are converted from the old per-piece production-stage
     * rates into a product-level snapshot so their quoted cost does not change
     * just because the screen changed. Products created after this migration
     * are explicitly placed in product_breakdown mode by ProductController.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('costing_mode', 30)->default('legacy')->after('print_type');
            $table->decimal('printing_cost', 14, 4)->default(0)->after('costing_mode');
            $table->decimal('production_cost', 14, 4)->default(0)->after('printing_cost');
            $table->decimal('sewing_cost', 14, 4)->default(0)->after('production_cost');
            $table->decimal('plastic_cost', 14, 4)->default(0)->after('sewing_cost');
            $table->decimal('box_cost', 14, 4)->default(0)->after('plastic_cost');
            $table->decimal('sticker_cost', 14, 4)->default(0)->after('box_cost');
        });

        // Ribbings is a normal material group in apparel costing and should be
        // available beside Fabric and Accessories without manual SQL setup.
        if (! DB::table('material_categories')->where('name', 'Ribbings')->exists()) {
            DB::table('material_categories')->insert([
                'name' => 'Ribbings',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('production_stages')) {
            return;
        }

        $rates = DB::table('production_stages')->pluck('rate', 'key')->map(fn ($rate) => (float) $rate);
        $stageDefinitions = ProductionPipeline::stages();
        $printingStages = ['printing', 'embroidery', 'press_roller', 'press_small'];

        DB::table('products')->orderBy('id')->get()->each(function ($product) use ($rates, $stageDefinitions, $printingStages) {
            if (blank($product->print_type)) {
                DB::table('products')->where('id', $product->id)->update(['costing_mode' => 'product_breakdown']);

                return;
            }

            $printing = 0.0;
            $production = 0.0;
            $sewing = 0.0;

            foreach (ProductionPipeline::stagesFor($product->print_type) as $stage) {
                if (($stageDefinitions[$stage]['basis'] ?? null) !== ProductionPipeline::PER_PIECE) {
                    continue;
                }

                $rate = (float) ($rates[$stage] ?? 0);

                if ($stage === 'sewing') {
                    $sewing += $rate;
                } elseif (in_array($stage, $printingStages, true)) {
                    $printing += $rate;
                } else {
                    $production += $rate;
                }
            }

            DB::table('products')->where('id', $product->id)->update([
                'costing_mode' => 'product_breakdown',
                'printing_cost' => $printing,
                'production_cost' => $production,
                'sewing_cost' => $sewing,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'costing_mode', 'printing_cost', 'production_cost', 'sewing_cost',
                'plastic_cost', 'box_cost', 'sticker_cost',
            ]);
        });
    }
};
