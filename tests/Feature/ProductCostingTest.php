<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Simple product costing: a product is its materials times their quantities,
 * totalled at both the bulk and the retail price.
 */
class ProductCostingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'SUPER ADMIN', 'guard_name' => 'web']);
        foreach (['manage products', 'manage materials', 'view internal costs'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function material(string $sku, float $bulk, ?float $retail = null, float $waste = 0): Material
    {
        $category = MaterialCategory::firstOrCreate(['name' => 'Blanks']);
        $material = Material::create([
            'sku' => $sku, 'name' => $sku.' material',
            'material_category_id' => $category->id, 'unit' => 'piece',
            'retail_cost' => $retail, 'waste_percentage' => $waste, 'is_active' => true,
        ]);
        $material->forceFill(['current_cost' => $bulk])->save();

        return $material;
    }

    public function test_a_product_costs_its_materials_times_their_quantities(): void
    {
        $blank = $this->material('TEE-BLANK', bulk: 65, retail: 80);
        $thread = $this->material('THREAD', bulk: 0.06, retail: 0.10);
        $product = Product::create([
            'sku' => 'TEE', 'name' => 'Tee',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);

        $product->materials()->sync([
            $blank->id => ['quantity' => 1],
            $thread->id => ['quantity' => 250],
        ]);

        $costing = $product->fresh()->costing();

        // 1 x 65 + 250 x 0.06 = 80.00 bulk; 1 x 80 + 250 x 0.10 = 105.00 retail.
        $this->assertEqualsWithDelta(80.00, $costing['bulk'], 0.001);
        $this->assertEqualsWithDelta(105.00, $costing['retail'], 0.001);
        $this->assertCount(2, $costing['lines']);
    }

    public function test_a_material_without_a_retail_price_falls_back_to_its_bulk_price(): void
    {
        $blank = $this->material('NO-RETAIL', bulk: 40, retail: null);
        $product = Product::create([
            'sku' => 'FALLBACK', 'name' => 'Fallback',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);
        $product->materials()->sync([$blank->id => ['quantity' => 2]]);

        $costing = $product->fresh()->costing();

        // A blank retail price must not read as free.
        $this->assertEqualsWithDelta(80.00, $costing['bulk'], 0.001);
        $this->assertEqualsWithDelta(80.00, $costing['retail'], 0.001);
        $this->assertTrue($costing['lines'][0]['estimated_retail']);
    }

    public function test_the_product_form_saves_the_quantity_of_each_material(): void
    {
        $this->admin();
        $blank = $this->material('SAVE-BLANK', bulk: 65, retail: 80);
        $category = ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel']);

        $this->post('/admin/products', [
            'sku' => 'QTY-TEE', 'name' => 'Quantity Tee',
            'product_category_id' => $category->id,
            'materials' => [$blank->id],
            'material_quantities' => [$blank->id => 3],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $product = Product::where('sku', 'QTY-TEE')->sole();
        $this->assertEqualsWithDelta(3.0, (float) $product->materials->first()->pivot->quantity, 0.001);
        $this->assertEqualsWithDelta(195.00, $product->costing()['bulk'], 0.001);
    }

    public function test_a_missing_quantity_counts_as_one_rather_than_zero(): void
    {
        $this->admin();
        $blank = $this->material('DEFAULT-QTY', bulk: 50);
        $category = ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel']);

        // No material_quantities at all: a product must not cost nothing.
        $this->post('/admin/products', [
            'sku' => 'NO-QTY', 'name' => 'No Quantity',
            'product_category_id' => $category->id,
            'materials' => [$blank->id],
        ])->assertSessionHasNoErrors();

        $product = Product::where('sku', 'NO-QTY')->sole();
        $this->assertEqualsWithDelta(50.00, $product->costing()['bulk'], 0.001);
    }

    public function test_the_form_offers_a_quantity_and_a_running_total(): void
    {
        $this->admin();
        $material = $this->material('FORM-QTY', bulk: 65, retail: 80);
        ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel']);

        $html = $this->get('/admin/products/create')->assertOk()->getContent();

        $this->assertStringContainsString('name="material_quantities['.$material->id.']"', $html);
        $this->assertStringContainsString('data-costing-summary', $html);
        $this->assertStringContainsString('data-material-total', $html);
        // The product preview receives only the one active material price.
        $this->assertStringContainsString('data-cost="80"', $html);
    }

    public function test_a_retail_material_uses_its_one_retail_price(): void
    {
        $this->admin();
        $category = MaterialCategory::firstOrCreate(['name' => 'Blanks']);

        $this->post('/admin/materials', [
            'sku' => 'RETAIL-SAVE', 'name' => 'Retail Save',
            'material_category_id' => $category->id, 'unit' => 'piece',
            'waste_percentage' => 0, 'price_type' => 'retail', 'retail_cost' => 88.5,
            'cost' => 65, 'effective_from' => '2026-09-02', 'reason' => 'Opening cost',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $material = Material::where('sku', 'RETAIL-SAVE')->firstOrFail();
        $this->assertEqualsWithDelta(88.5, (float) $material->retail_cost, 0.001);
        $this->assertEqualsWithDelta(88.5, (float) $material->costOn('2026-09-02'), 0.001);
    }

    public function test_a_bulk_material_price_is_calculated_from_total_divided_by_quantity(): void
    {
        $this->admin();
        $category = MaterialCategory::firstOrCreate(['name' => 'Blanks']);

        $this->post('/admin/materials', [
            'sku' => 'BULK-DIVIDE', 'name' => 'Bulk Divide',
            'material_category_id' => $category->id, 'unit' => 'piece',
            'waste_percentage' => 0, 'price_type' => 'bulk',
            'bulk_total' => 650, 'bulk_quantity' => 10,
            // The server must not accept a fake lower unit cost when the bulk
            // invoice values are present.
            'cost' => 1, 'effective_from' => '2026-09-08', 'reason' => 'Bulk purchase',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $material = Material::where('sku', 'BULK-DIVIDE')->firstOrFail();
        $this->assertEqualsWithDelta(65, (float) $material->costOn('2026-09-08'), 0.0001);
        $this->assertNull($material->retail_cost);
    }

    public function test_waste_is_charged_on_top_of_the_quantity_used(): void
    {
        // 8% waste means 1.08 metres are bought for every metre delivered.
        $film = $this->material('FILM', bulk: 100, retail: 120, waste: 8);
        $product = Product::create([
            'sku' => 'WASTE', 'name' => 'Waste',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);
        $product->materials()->sync([$film->id => ['quantity' => 2]]);

        $costing = $product->fresh()->costing();

        // 2 x 1.08 = 2.16 consumed; x100 bulk, x120 retail.
        $this->assertEqualsWithDelta(216.00, $costing['bulk'], 0.001);
        $this->assertEqualsWithDelta(259.20, $costing['retail'], 0.001);
        $this->assertEqualsWithDelta(2.16, $costing['lines'][0]['consumed'], 0.0001);
    }

    public function test_a_material_without_waste_costs_exactly_its_quantity(): void
    {
        $blank = $this->material('NO-WASTE', bulk: 50, retail: 60, waste: 0);
        $product = Product::create([
            'sku' => 'PLAIN', 'name' => 'Plain',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);
        $product->materials()->sync([$blank->id => ['quantity' => 3]]);

        $this->assertEqualsWithDelta(150.00, $product->fresh()->costing()['bulk'], 0.001);
    }

    public function test_the_costing_reports_the_gap_between_bulk_and_retail(): void
    {
        $blank = $this->material('GAP', bulk: 80, retail: 100);
        $product = Product::create([
            'sku' => 'GAP-P', 'name' => 'Gap',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);
        $product->materials()->sync([$blank->id => ['quantity' => 1]]);

        $costing = $product->fresh()->costing();
        $this->assertEqualsWithDelta(20.00, $costing['difference'], 0.001);
        $this->assertEqualsWithDelta(25.00, $costing['markup_percentage'], 0.001);
    }

    public function test_a_retail_price_below_bulk_reports_a_negative_gap(): void
    {
        // Worth seeing rather than hiding: retail under bulk sells at a loss.
        $blank = $this->material('LOSS', bulk: 100, retail: 90);
        $product = Product::create([
            'sku' => 'LOSS-P', 'name' => 'Loss',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);
        $product->materials()->sync([$blank->id => ['quantity' => 1]]);

        $costing = $product->fresh()->costing();
        $this->assertEqualsWithDelta(-10.00, $costing['difference'], 0.001);
        $this->assertEqualsWithDelta(-10.00, $costing['markup_percentage'], 0.001);
    }

    public function test_a_product_with_no_materials_reports_zero_without_dividing_by_it(): void
    {
        $product = Product::create([
            'sku' => 'NONE', 'name' => 'None',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);

        $costing = $product->costing();
        $this->assertSame(0.0, $costing['bulk']);
        $this->assertSame(0.0, $costing['markup_percentage']);
    }

    public function test_a_product_costs_its_materials_and_no_labour(): void
    {
        \App\Models\Setting::updateOrCreate(['key' => 'default_labour_cost'], ['value' => '25']);
        $blank = $this->material('MARGIN', bulk: 60, retail: 60);
        $product = Product::create([
            'sku' => 'MARGIN-P', 'name' => 'Margin',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);
        $product->materials()->sync([$blank->id => ['quantity' => 1]]);

        // What the shop charges for work belongs to the quotation, not here.
        $costing = $product->fresh()->costing();
        $this->assertEqualsWithDelta(60.00, $costing['retail'], 0.001);
        $this->assertArrayNotHasKey('labour', $costing);
    }

    public function test_a_product_with_no_materials_costs_nothing_whatever_the_shop_charges(): void
    {
        // The guard that refuses to quote an empty product reads this figure,
        // so a labour rate must never make an empty product look costed.
        \App\Models\Setting::updateOrCreate(['key' => 'default_labour_cost'], ['value' => '25']);
        $product = Product::create([
            'sku' => 'HOLLOW', 'name' => 'Hollow',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);

        $costing = $product->fresh()->costing();
        $this->assertEqualsWithDelta(0.0, $costing['bulk'], 0.001);
        $this->assertEqualsWithDelta(0.0, $costing['retail'], 0.001);
    }


    public function test_a_consumable_with_a_coverage_rate_is_charged_by_the_printed_area(): void
    {
        // Ink bought by the gram at 1 peso, 100 grams to a square metre.
        $ink = $this->material('INK', bulk: 1.00, retail: 1.00);
        $ink->forceFill(['unit' => 'gram', 'coverage_per_sqm' => 100])->save();
        $product = Product::create([
            'sku' => 'INK-P', 'name' => 'Ink product',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);
        $product->materials()->sync([$ink->id => ['quantity' => 1]]);
        $product = $product->fresh();

        $this->assertTrue($product->requiresArtworkSize());

        // Half a square metre of print takes 50 grams, so 50 pesos.
        $this->assertEqualsWithDelta(50.00, $product->costing(5000.0)['retail'], 0.001);
        // A tenth of that print takes a tenth of the ink.
        $this->assertEqualsWithDelta(5.00, $product->costing(500.0)['retail'], 0.001);
    }

    public function test_a_bigger_print_uses_more_of_a_covered_consumable(): void
    {
        $ink = $this->material('INK2', bulk: 2.00, retail: 2.00);
        $ink->forceFill(['unit' => 'gram', 'coverage_per_sqm' => 80])->save();
        $product = Product::create([
            'sku' => 'INK2-P', 'name' => 'Ink product 2',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);
        $product->materials()->sync([$ink->id => ['quantity' => 1]]);
        $product = $product->fresh();

        $small = $product->costing(25.0)['retail'];
        $large = $product->costing(900.0)['retail'];
        $this->assertGreaterThan($small, $large);
        // Thirty-six times the area, thirty-six times the ink.
        $this->assertEqualsWithDelta(36.0, $large / $small, 0.001);
    }

    public function test_a_consumable_without_a_coverage_rate_stays_counted_per_garment(): void
    {
        $powder = $this->material('POWDER', bulk: 0.38, retail: 0.38);
        $powder->forceFill(['unit' => 'gram', 'coverage_per_sqm' => null])->save();
        $product = Product::create([
            'sku' => 'POWDER-P', 'name' => 'Powder product',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);
        $product->materials()->sync([$powder->id => ['quantity' => 1]]);
        $product = $product->fresh();

        $this->assertFalse($product->requiresArtworkSize());
        // Same gram whatever the artwork, exactly as before coverage existed.
        $this->assertEqualsWithDelta(0.38, $product->costing(25.0)['retail'], 0.001);
        $this->assertEqualsWithDelta(0.38, $product->costing(9000.0)['retail'], 0.001);
    }

    public function test_a_material_sold_by_area_says_how_much_print_one_unit_covers(): void
    {
        $film = $this->material('FILM-AREA', bulk: 51, retail: 51);
        $film->forceFill(['unit' => 'square_meter'])->save();

        // A square metre is ten thousand square centimetres of print.
        $this->assertEqualsWithDelta(10000.0, $film->fresh()->areaDivisorCm2(), 0.001);
    }

    public function test_a_consumable_covers_the_print_its_coverage_rate_allows(): void
    {
        $ink = $this->material('INK-AREA', bulk: 1, retail: 1);
        $ink->forceFill(['unit' => 'gram', 'coverage_per_sqm' => 100])->save();

        // A hundred grams to the square metre is one gram per hundred cm2.
        $this->assertEqualsWithDelta(100.0, $ink->fresh()->areaDivisorCm2(), 0.001);
    }

    public function test_a_counted_material_is_not_measured_by_area(): void
    {
        $blank = $this->material('BLANK-AREA', bulk: 65, retail: 65);

        $this->assertNull($blank->fresh()->areaDivisorCm2());
    }

    public function test_the_product_form_quotes_area_materials_as_a_rate_not_a_whole_unit(): void
    {
        $this->admin();
        $blank = $this->material('PF-BLANK', bulk: 65, retail: 65);
        $film = $this->material('PF-FILM', bulk: 51, retail: 51);
        $film->forceFill(['unit' => 'square_meter'])->save();

        $product = Product::create([
            'sku' => 'PF-TEE', 'name' => 'Preview Tee',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);
        $product->materials()->sync([$blank->id => ['quantity' => 1], $film->id => ['quantity' => 1]]);

        // The form hands the browser how much print a unit covers, so the
        // preview can charge film by the square centimetre instead of
        // pricing a whole square metre into every shirt.
        $this->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee('data-per-cm2="10000"', false)
            ->assertSee('by artwork size');
    }
}
