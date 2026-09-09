<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionStage;
use App\Models\QuantityBreak;
use App\Models\Quotation;
use App\Models\RushTier;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Quotations price products from their materials, and keep that price.
 */
class QuotationTest extends TestCase
{
    use RefreshDatabase;

    /** Older test quotes predate the now-required print-location field. */
    public function post($uri, array $data = [], array $headers = [])
    {
        return parent::post($uri, $this->withLocations($uri, $data), $headers);
    }

    public function put($uri, array $data = [], array $headers = [])
    {
        return parent::put($uri, $this->withLocations($uri, $data), $headers);
    }

    private function withLocations(string $uri, array $data): array
    {
        if (str_starts_with($uri, '/quotations') && isset($data['items'])) {
            $data['items'] = array_map(function (array $item) {
                $item['print_location'] ??= 'Front';

                return $item;
            }, $data['items']);
        }

        return $data;
    }

    private function user(): User
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

    /**
     * A product costing $bulk in bulk and $retail at retail, for one unit.
     *
     * Every product says how it is made: without a route there is no telling
     * which stages of the floor it occupies, and it cannot be quoted.
     */
    private function product(string $sku, float $bulk, float $retail, string $printType = 'dtf'): Product
    {
        $material = Material::create([
            'sku' => $sku.'-MAT', 'name' => $sku.' material',
            'material_category_id' => MaterialCategory::firstOrCreate(['name' => 'Fabric'], ['is_active' => true])->id,
            'unit' => 'piece', 'retail_cost' => $retail, 'is_active' => true,
        ]);
        $material->forceFill(['current_cost' => $bulk])->save();

        $product = Product::create([
            'sku' => $sku, 'name' => $sku.' product', 'is_active' => true,
            'print_type' => $printType,
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);
        $product->materials()->sync([$material->id => ['quantity' => 1]]);

        return $product;
    }

    /**
     * Put all the per-piece work on one stage, so a test can talk about "the
     * labour on a piece" without naming the whole floor. Sewing is on every
     * route, so this is what a piece costs in work whatever the print type.
     */
    private function labourDefault(float $amount): void
    {
        $this->stageRate('sewing', $amount);
    }

    /** One stage of the floor costs this much. */
    private function stageRate(string $key, float $rate): void
    {
        ProductionStage::where('key', $key)->update(['rate' => $rate]);
        app()->forgetInstance('costing.stage_rates');
    }

    /** The default tier shipped by the migration must not collide with a test. */
    private function rushTier(int $days, float $surcharge): RushTier
    {
        return RushTier::updateOrCreate(
            ['within_days' => $days],
            ['surcharge_percentage' => $surcharge]
        );
    }

    public function test_a_quotation_prices_each_line_from_the_products_materials(): void
    {
        $this->user();
        $tee = $this->product('TEE', bulk: 65, retail: 80);
        $cap = $this->product('CAP', bulk: 100, retail: 130);

        $this->post('/quotations', [
            'customer_name' => 'Falcon Riders MC',
            'items' => [
                ['product_id' => $tee->id, 'quantity' => 20, 'print_location' => 'Front chest'],
                ['product_id' => $cap->id, 'quantity' => 5],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $quotation = Quotation::with('items')->sole();

        // 20 x 80 = 1,600 and 5 x 130 = 650.
        $this->assertEqualsWithDelta(1600.00, (float) $quotation->items[0]->line_total, 0.01);
        $this->assertEqualsWithDelta(650.00, (float) $quotation->items[1]->line_total, 0.01);
        $this->assertEqualsWithDelta(2250.00, (float) $quotation->total, 0.01);
        $this->assertSame('Front chest', $quotation->items[0]->print_location);
        $this->assertSame('QT-'.now()->format('Y').'-000001', $quotation->number);
    }


    public function test_a_saved_quotation_keeps_its_price_when_a_material_is_repriced(): void
    {
        $this->user();
        $tee = $this->product('TEE', bulk: 65, retail: 80);

        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $tee->id, 'quantity' => 10]],
        ])->assertSessionHasNoErrors();

        // The material doubles in price after the customer was quoted.
        $material = $tee->materials->first();
        $material->forceFill(['current_cost' => 130, 'retail_cost' => 160])->save();

        $quotation = Quotation::with('items')->sole();
        $this->assertEqualsWithDelta(80.00, (float) $quotation->items[0]->unit_price, 0.01);
        $this->assertEqualsWithDelta(800.00, (float) $quotation->total, 0.01);
    }

    public function test_a_product_with_no_priced_materials_cannot_be_quoted(): void
    {
        $this->user();
        $empty = Product::create([
            'sku' => 'EMPTY', 'name' => 'Empty', 'is_active' => true,
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);

        // Quoting zero to a customer is worse than refusing to quote.
        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $empty->id, 'quantity' => 5]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Quotation::count());
    }

    public function test_the_same_product_cannot_be_quoted_twice_on_one_quotation(): void
    {
        $this->user();
        $tee = $this->product('TEE', bulk: 65, retail: 80);

        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [
                ['product_id' => $tee->id, 'quantity' => 5],
                ['product_id' => $tee->id, 'quantity' => 3],
            ],
        ])->assertSessionHasErrors('items.1.product_id');

        $this->assertSame(0, Quotation::count());
    }

    public function test_a_quotation_needs_at_least_one_line(): void
    {
        $this->user();

        $this->post('/quotations', ['customer_name' => 'Buyer'])
            ->assertSessionHasErrors('items');
    }

    public function test_the_quotation_screens_render(): void
    {
        $this->user();
        $tee = $this->product('TEE', bulk: 65, retail: 80);

        $this->post('/quotations', [
            'customer_name' => 'Falcon Riders MC',
            'items' => [['product_id' => $tee->id, 'quantity' => 20]],
        ]);
        $quotation = Quotation::sole();

        $this->get('/quotations')->assertOk()->assertSee('Falcon Riders MC');
        $this->get('/quotations/create')->assertOk()->assertSee('TEE');
        $this->get('/quotations/'.$quotation->id)->assertOk()->assertSee('1,600.00');
        $this->get('/quotations/'.$quotation->id.'/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_form_never_accepts_a_price_from_the_browser(): void
    {
        $this->user();
        $tee = $this->product('TEE', bulk: 65, retail: 80);

        // A forged unit price must be ignored: prices come from the product.
        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $tee->id, 'quantity' => 1, 'unit_price' => 1, 'line_total' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(80.00, (float) Quotation::sole()->total, 0.01);
    }

    public function test_an_existing_customer_can_be_picked_for_a_quotation(): void
    {
        $this->user();
        $tee = $this->product('TEE', bulk: 65, retail: 80);
        $customer = \App\Models\Customer::create([
            'code' => \App\Models\Customer::nextCode(),
            'contact_name' => 'Juan Dela Cruz', 'company_name' => 'Falcon Riders MC',
            'phone' => '0917-555-1234', 'is_active' => true,
        ]);

        $this->post('/quotations', [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $tee->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::sole();
        $this->assertSame($customer->id, $quotation->customer_id);
        // The label is snapshotted so the quotation still names them if the
        // customer record is later renamed or removed.
        $this->assertSame('Falcon Riders MC', $quotation->customer_name);
        $this->assertSame(1, \App\Models\Customer::count(), 'Picking must not create a second customer');
    }

    public function test_a_new_customer_typed_on_the_quotation_is_saved_with_it(): void
    {
        $this->user();
        $tee = $this->product('TEE', bulk: 65, retail: 80);

        $this->post('/quotations', [
            'customer_name' => 'Maria Santos',
            'customer_company' => 'Santos Trading',
            'customer_contact' => '0918-555-2345',
            'items' => [['product_id' => $tee->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $customer = \App\Models\Customer::sole();
        $this->assertSame('Maria Santos', $customer->contact_name);
        $this->assertSame('Santos Trading', $customer->company_name);
        $this->assertSame($customer->id, Quotation::sole()->customer_id);
    }

    public function test_a_quotation_needs_a_customer_one_way_or_the_other(): void
    {
        $this->user();
        $tee = $this->product('TEE', bulk: 65, retail: 80);

        $this->post('/quotations', [
            'items' => [['product_id' => $tee->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('customer_name');

        $this->assertSame(0, Quotation::count());
    }

    public function test_a_quotation_survives_its_customer_being_deleted(): void
    {
        $this->user();
        $tee = $this->product('TEE', bulk: 65, retail: 80);
        $this->post('/quotations', [
            'customer_name' => 'Short Lived',
            'items' => [['product_id' => $tee->id, 'quantity' => 1]],
        ]);

        \App\Models\Customer::sole()->delete();

        $quotation = Quotation::sole();
        $this->assertNull($quotation->customer_id);
        $this->assertSame('Short Lived', $quotation->customer_name);
    }

    /** A product whose print is on film sold by the square metre. */
    private function printedProduct(string $sku, float $blank, float $filmPerSqm): Product
    {
        $category = MaterialCategory::firstOrCreate(['name' => 'Fabric'], ['is_active' => true]);

        $blankMaterial = Material::create([
            'sku' => $sku.'-BLANK', 'name' => 'Blank', 'material_category_id' => $category->id,
            'unit' => 'piece', 'retail_cost' => $blank, 'is_active' => true,
        ]);
        $blankMaterial->forceFill(['current_cost' => $blank])->save();

        $film = Material::create([
            'sku' => $sku.'-FILM', 'name' => 'Transfer film', 'material_category_id' => $category->id,
            'unit' => 'square_meter', 'retail_cost' => $filmPerSqm, 'is_active' => true,
        ]);
        $film->forceFill(['current_cost' => $filmPerSqm])->save();

        $product = Product::create([
            'sku' => $sku, 'name' => $sku.' printed', 'is_active' => true,
            'print_type' => 'dtf',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);
        $product->materials()->sync([
            $blankMaterial->id => ['quantity' => 1],
            $film->id => ['quantity' => 1],
        ]);

        return $product;
    }

    public function test_a_printed_product_is_costed_by_its_artwork_size(): void
    {
        $this->user();
        $tee = $this->printedProduct('TEE', blank: 65, filmPerSqm: 50);

        // 20 x 25 cm is 500 cm2, a twentieth of a square metre: P2.50 of film.
        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [[
                'product_id' => $tee->id, 'quantity' => 10,
                'artwork_width_cm' => 20, 'artwork_height_cm' => 25,
            ]],
        ])->assertSessionHasNoErrors();

        $item = Quotation::with('items')->sole()->items->first();
        $this->assertEqualsWithDelta(67.50, (float) $item->unit_price, 0.01);
        $this->assertEqualsWithDelta(675.00, (float) $item->line_total, 0.01);
        $this->assertEqualsWithDelta(500.0, $item->artworkAreaCm2(), 0.01);
    }

    public function test_a_bigger_print_costs_more(): void
    {
        $this->user();
        $tee = $this->printedProduct('TEE', blank: 65, filmPerSqm: 50);

        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [[
                'product_id' => $tee->id, 'quantity' => 1,
                'artwork_width_cm' => 40, 'artwork_height_cm' => 50,
            ]],
        ])->assertSessionHasNoErrors();

        // 2,000 cm2 is four times the area, so four times the film: P10.
        $this->assertEqualsWithDelta(75.00, (float) Quotation::sole()->total, 0.01);
    }

    public function test_an_additional_print_location_is_saved_and_added_to_the_printed_area(): void
    {
        $this->user();
        $tee = $this->printedProduct('TEE', blank: 65, filmPerSqm: 50);

        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [[
                'product_id' => $tee->id, 'quantity' => 1,
                'artwork_width_cm' => 10, 'artwork_height_cm' => 10,
                'additional_locations' => [['location' => 'Back', 'width' => 20, 'height' => 10]],
            ]],
        ])->assertSessionHasNoErrors();

        $item = Quotation::with('items.additionalLocations')->sole()->items->first();
        $this->assertSame('Back', $item->additionalLocations->sole()->location);
        // 300 cm² total: P1.50 film plus the P65 blank, at no margin.
        $this->assertEqualsWithDelta(66.50, (float) $item->unit_price, 0.01);
    }

    public function test_a_printed_product_cannot_be_quoted_without_an_artwork_size(): void
    {
        $this->user();
        $tee = $this->printedProduct('TEE', blank: 65, filmPerSqm: 50);

        // Without a size the film cannot be costed, and the line would come out
        // at the price of the blank alone.
        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $tee->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Quotation::count());
    }

    public function test_a_product_with_no_area_material_needs_no_artwork_size(): void
    {
        $this->user();
        $mug = $this->product('MUG', bulk: 60, retail: 75);

        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $mug->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(150.00, (float) Quotation::sole()->total, 0.01);
        $this->assertNull(Quotation::with('items')->sole()->items->first()->artworkAreaCm2());
    }

    public function test_the_margin_is_a_share_of_the_selling_price(): void
    {
        $this->user();
        Setting::create(['key' => 'default_margin', 'value' => '40']);
        $product = $this->product('MARGIN', bulk: 60, retail: 60);

        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $item = Quotation::with('items')->sole()->items->sole();

        // A 40% margin on a 60 peso cost sells at 100 and keeps 40 - a share
        // of the price, not of the cost.
        $this->assertEqualsWithDelta(60.00, (float) $item->unit_cost, 0.01);
        $this->assertEqualsWithDelta(100.00, (float) $item->unit_price, 0.01);
    }

    public function test_a_margin_of_a_hundred_percent_is_refused_rather_than_dividing_by_zero(): void
    {
        $this->user();
        Setting::create(['key' => 'default_margin', 'value' => '100']);
        $product = $this->product('ABSURD', bulk: 20, retail: 20);

        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(20.00, (float) Quotation::sole()->total, 0.01);
    }

    public function test_labour_is_added_to_the_line_on_top_of_its_materials(): void
    {
        $this->user();
        $this->labourDefault(60);
        $product = $this->product('LAB', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $item = Quotation::with('items')->sole()->items->sole();
        $this->assertEqualsWithDelta(110.00, (float) $item->unit_cost, 0.01);
        $this->assertEqualsWithDelta(60.00, (float) $item->labour_cost, 0.01);
    }

    public function test_with_no_labour_set_a_line_is_charged_none(): void
    {
        $this->user();
        $product = $this->product('NOLAB', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $item = Quotation::with('items')->sole()->items->sole();
        $this->assertEqualsWithDelta(0.0, (float) $item->labour_cost, 0.01);
        $this->assertEqualsWithDelta(50.00, (float) $item->unit_price, 0.01);
    }

    public function test_an_empty_product_cannot_be_quoted_even_when_labour_is_set(): void
    {
        $this->user();
        // The bug this guards: labour folded into the product made a product
        // with no materials look costed, and quotable at labour alone.
        $this->labourDefault(25);
        $empty = Product::create([
            'sku' => 'HOLLOW', 'name' => 'Hollow', 'is_active' => true,
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
        ]);

        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $empty->id, 'quantity' => 5]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Quotation::count());
    }

    public function test_a_bigger_order_earns_the_volume_discount(): void
    {
        $this->user();
        // A 50% margin puts the list price at twice the cost, so a discount has
        // room to come off before it reaches the cost floor.
        Setting::create(['key' => 'default_margin', 'value' => '50']);
        QuantityBreak::create(['min_quantity' => 50, 'discount_percentage' => 10]);
        QuantityBreak::create(['min_quantity' => 100, 'discount_percentage' => 20]);
        $product = $this->product('VOL', bulk: 10, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Bulk buyer',
            'items' => [['product_id' => $product->id, 'quantity' => 100]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $item = Quotation::with('items')->sole()->items->sole();

        // A hundred pieces reaches the 20% tier, not the 10% one below it.
        $this->assertEqualsWithDelta(80.00, (float) $item->unit_price, 0.01);
        $this->assertEqualsWithDelta(20.0, (float) $item->discount_percentage, 0.01);
        $this->assertEqualsWithDelta(8000.00, (float) $item->line_total, 0.01);
    }

    public function test_an_order_below_every_break_pays_the_full_price(): void
    {
        $this->user();
        QuantityBreak::create(['min_quantity' => 50, 'discount_percentage' => 10]);
        $product = $this->product('SMALL', bulk: 10, retail: 100);

        $this->post('/quotations', [
            'customer_name' => 'Small buyer',
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ])->assertSessionHasNoErrors();

        $item = Quotation::with('items')->sole()->items->sole();
        $this->assertEqualsWithDelta(100.00, (float) $item->unit_price, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $item->discount_percentage, 0.01);
    }

    public function test_a_discount_is_held_at_cost_rather_than_selling_at_a_loss(): void
    {
        $this->user();
        // A 90% break against a product whose price is barely above its cost.
        QuantityBreak::create(['min_quantity' => 10, 'discount_percentage' => 90]);
        $product = $this->product('FLOOR', bulk: 100, retail: 100);

        $this->post('/quotations', [
            'customer_name' => 'Chancer',
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ])->assertSessionHasNoErrors();

        $item = Quotation::with('items')->sole()->items->sole();
        $this->assertEqualsWithDelta(100.00, (float) $item->unit_price, 0.01);
        $this->assertEqualsWithDelta(100.00, (float) $item->unit_cost, 0.01);
    }

    public function test_a_line_snapshots_the_cost_it_was_priced_from(): void
    {
        $this->user();
        $product = $this->product('SNAP', bulk: 60, retail: 60);

        $this->post('/quotations', [
            'customer_name' => 'Snapshot',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $item = Quotation::with('items')->sole()->items->sole();
        $this->assertEqualsWithDelta(60.00, (float) $item->unit_cost, 0.01);

        // Repricing the material afterwards must not rewrite what this cost.
        $product->materials->first()->forceFill(['current_cost' => 999, 'retail_cost' => 999])->save();
        $this->assertEqualsWithDelta(60.00, (float) $item->fresh()->unit_cost, 0.01);
    }

    public function test_a_quotation_can_be_edited_and_is_repriced_from_current_costs(): void
    {
        $this->user();
        $tee = $this->product('EDIT-TEE', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Editable',
            'items' => [['product_id' => $tee->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::sole();
        $this->assertEqualsWithDelta(100.00, (float) $quotation->total, 0.01);

        $this->get("/quotations/{$quotation->id}/edit")->assertOk()->assertSee('EDIT-TEE');

        // The material doubles, and the quantity changes with it.
        $tee->materials->first()->forceFill(['current_cost' => 100, 'retail_cost' => 100])->save();

        $this->put("/quotations/{$quotation->id}", [
            'customer_name' => 'Editable',
            'items' => [['product_id' => $tee->id, 'quantity' => 3]],
        ])->assertSessionHasNoErrors()->assertRedirect(route('quotations.show', $quotation));

        $quotation = $quotation->fresh('items');
        $this->assertCount(1, $quotation->items);
        $this->assertEqualsWithDelta(3.0, (float) $quotation->items->sole()->quantity, 0.01);
        $this->assertEqualsWithDelta(300.00, (float) $quotation->total, 0.01);
        // Editing keeps the number the customer was given.
        $this->assertSame('QT-'.now()->format('Y').'-000001', $quotation->number);
    }

    public function test_editing_replaces_the_lines_rather_than_adding_to_them(): void
    {
        $this->user();
        $tee = $this->product('REPL-TEE', bulk: 50, retail: 50);
        $cap = $this->product('REPL-CAP', bulk: 30, retail: 30);

        $this->post('/quotations', [
            'customer_name' => 'Replacer',
            'items' => [
                ['product_id' => $tee->id, 'quantity' => 1],
                ['product_id' => $cap->id, 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::sole();

        $this->put("/quotations/{$quotation->id}", [
            'customer_name' => 'Replacer',
            'items' => [['product_id' => $cap->id, 'quantity' => 4]],
        ])->assertSessionHasNoErrors();

        $quotation = $quotation->fresh('items');
        $this->assertCount(1, $quotation->items);
        $this->assertSame('REPL-CAP', $quotation->items->sole()->product_sku);
        $this->assertEqualsWithDelta(120.00, (float) $quotation->total, 0.01);
    }

    public function test_a_quotation_can_be_duplicated_at_current_prices(): void
    {
        $this->user();
        $tee = $this->product('DUP-TEE', bulk: 40, retail: 40);

        $this->post('/quotations', [
            'customer_name' => 'Repeat customer',
            'items' => [['product_id' => $tee->id, 'quantity' => 5]],
        ])->assertSessionHasNoErrors();

        $original = Quotation::sole();
        $tee->materials->first()->forceFill(['current_cost' => 60, 'retail_cost' => 60])->save();

        $this->post("/quotations/{$original->id}/duplicate")->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(2, Quotation::count());
        $copy = Quotation::with('items')->latest('id')->first();

        // A new number, the same lines, priced afresh.
        $this->assertNotSame($original->number, $copy->number);
        $this->assertSame('Repeat customer', $copy->customer_name);
        $this->assertEqualsWithDelta(5.0, (float) $copy->items->sole()->quantity, 0.01);
        $this->assertEqualsWithDelta(300.00, (float) $copy->total, 0.01);
        // The original is left exactly as the customer received it.
        $this->assertEqualsWithDelta(200.00, (float) $original->fresh()->total, 0.01);
    }

    /** The settings form posts every field, so a partial payload is not a test. */
    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Imprint Customs',
            'quotation_validity_days' => 15,
            'currency' => 'PHP',
            'tax_enabled' => 0,
            'tax_rate' => 0,
            'tax_inclusive' => 0,
            'quotation_prefix' => 'QT',
            'minimum_gross_margin' => 30,
        ], $overrides);
    }

    public function test_volume_discounts_are_saved_from_the_settings_form(): void
    {
        $this->user();

        $this->put('/admin/settings', $this->settingsPayload([
            'breaks' => [
                ['min_quantity' => 50, 'discount_percentage' => 10],
                ['min_quantity' => 100, 'discount_percentage' => 20],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, QuantityBreak::count());
        $this->assertEqualsWithDelta(
            20.0,
            (float) QuantityBreak::where('min_quantity', 100)->sole()->discount_percentage,
            0.01
        );
    }

    public function test_a_break_cleared_on_the_form_stops_discounting(): void
    {
        $this->user();
        QuantityBreak::create(['min_quantity' => 50, 'discount_percentage' => 10]);

        // A row with the quantity cleared is a removal, not a blank break.
        $this->put('/admin/settings', $this->settingsPayload([
            'breaks' => [['min_quantity' => null, 'discount_percentage' => 10]],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(0, QuantityBreak::count());
        $this->assertEqualsWithDelta(0.0, QuantityBreak::discountFor(500), 0.01);
    }

    public function test_every_line_is_charged_the_shop_labour(): void
    {
        $this->user();
        $this->labourDefault(60);
        $product = $this->product('JOB', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Ordinary job',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $item = Quotation::with('items')->sole()->items->sole();

        // 50 of materials plus 60 of work is 110 a piece.
        $this->assertEqualsWithDelta(110.00, (float) $item->unit_price, 0.01);
        $this->assertEqualsWithDelta(60, (float) $item->labour_cost, 0.01);
        $this->assertEqualsWithDelta(220.00, (float) $item->line_total, 0.01);
    }

    public function test_a_blank_labour_box_falls_back_to_the_shop_default(): void
    {
        $this->user();
        $product = $this->product('USUAL', bulk: 50, retail: 50);
        $this->labourDefault(60);

        $this->post('/quotations', [
            'customer_name' => 'Ordinary job',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'labour_cost' => null]],
        ])->assertSessionHasNoErrors();

        $item = Quotation::with('items')->sole()->items->sole();
        $this->assertEqualsWithDelta(110.00, (float) $item->unit_price, 0.01);
        $this->assertEqualsWithDelta(60, (float) $item->labour_cost, 0.01);
    }

    public function test_a_shop_default_of_nothing_charges_no_labour(): void
    {
        $this->user();
        $this->labourDefault(0);
        $product = $this->product('FREE', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'No work',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $item = Quotation::with('items')->sole()->items->sole();
        $this->assertEqualsWithDelta(50.00, (float) $item->unit_price, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $item->labour_cost, 0.01);
    }

    public function test_the_same_labour_is_charged_on_every_line(): void
    {
        $this->user();
        $this->labourDefault(5);
        $simple = $this->product('SIMPLE', bulk: 20, retail: 20);
        $other = $this->product('OTHER', bulk: 30, retail: 30);

        $this->post('/quotations', [
            'customer_name' => 'Mixed job',
            'items' => [
                ['product_id' => $simple->id, 'quantity' => 1],
                ['product_id' => $other->id, 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $items = Quotation::with('items')->sole()->items;

        // The shop figure rides on each line, whatever the product costs.
        $this->assertEqualsWithDelta(25.00, (float) $items[0]->unit_price, 0.01);
        $this->assertEqualsWithDelta(35.00, (float) $items[1]->unit_price, 0.01);
    }

    public function test_labour_is_inside_the_cost_a_discount_may_not_cut_into(): void
    {
        $this->user();
        $this->labourDefault(60);
        $this->rushTier(0, 0);
        QuantityBreak::create(['min_quantity' => 10, 'discount_percentage' => 90]);
        $product = $this->product('FLOORLAB', bulk: 40, retail: 40);

        $this->post('/quotations', [
            'customer_name' => 'Chancer',
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ])->assertSessionHasNoErrors();

        $item = Quotation::with('items')->sole()->items->sole();

        // Materials 40 plus 60 of work is a floor of 100, not 40.
        $this->assertEqualsWithDelta(100.00, (float) $item->unit_cost, 0.01);
        $this->assertEqualsWithDelta(100.00, (float) $item->unit_price, 0.01);
    }

    public function test_a_saved_line_keeps_the_labour_it_was_quoted_at(): void
    {
        $this->user();
        $this->labourDefault(90);
        $product = $this->product('SNAPLAB', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Snapshot',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        // The shop raises its labour afterwards.
        $this->labourDefault(10);

        $item = Quotation::with('items')->sole()->items->sole();
        $this->assertEqualsWithDelta(90, (float) $item->fresh()->labour_cost, 0.01);
        $this->assertEqualsWithDelta(140.00, (float) $item->fresh()->unit_price, 0.01);
    }

    public function test_duplicating_reprices_labour_at_the_current_default(): void
    {
        $this->user();
        $this->labourDefault(60);
        $product = $this->product('DUPLAB', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Repeat',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $original = Quotation::sole();
        $this->assertEqualsWithDelta(110.00, (float) $original->total, 0.01);

        // Labour comes from settings, so a repeat is quoted at today's figure
        // exactly as its materials are.
        $this->labourDefault(20);
        $this->post("/quotations/{$original->id}/duplicate")->assertSessionHasNoErrors();

        $copy = Quotation::with('items')->latest('id')->first();
        $this->assertEqualsWithDelta(20, (float) $copy->items->sole()->labour_cost, 0.01);
        $this->assertEqualsWithDelta(70.00, (float) $copy->total, 0.01);
        // The original is left as the customer received it.
        $this->assertEqualsWithDelta(110.00, (float) $original->fresh()->total, 0.01);
    }

    public function test_the_quotation_form_asks_only_for_product_and_quantity(): void
    {
        $this->user();
        $this->labourDefault(15);
        $this->product('FORMLAB', bulk: 50, retail: 50);

        // Neither labour nor the route is chosen per job: the labour follows
        // from the stages the product's print type runs.
        $this->get('/quotations/create')
            ->assertOk()
            ->assertDontSee('labour_cost')
            ->assertDontSee('items[__i__][print_type]', false)
            ->assertSee('production stages');
    }

    public function test_a_deadline_within_a_week_adds_a_rush_fee(): void
    {
        $this->user();
        $this->rushTier(7, 25);
        $product = $this->product('RUSH', bulk: 50, retail: 100);

        $this->post('/quotations', [
            'customer_name' => 'In a hurry',
            'deadline' => today()->addDays(3)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::sole();

        // 10 x 100 is 1,000, plus a quarter for the deadline.
        $this->assertEqualsWithDelta(1000.00, (float) $quotation->subtotal, 0.01);
        $this->assertEqualsWithDelta(25.0, (float) $quotation->rush_percentage, 0.01);
        $this->assertEqualsWithDelta(250.00, (float) $quotation->rush_amount, 0.01);
        $this->assertEqualsWithDelta(1250.00, (float) $quotation->total, 0.01);
    }

    public function test_a_comfortable_deadline_pays_no_rush_fee(): void
    {
        $this->user();
        $this->rushTier(7, 25);
        $product = $this->product('CALM', bulk: 50, retail: 100);

        $this->post('/quotations', [
            'customer_name' => 'No hurry',
            'deadline' => today()->addDays(30)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::sole();
        $this->assertEqualsWithDelta(0.0, (float) $quotation->rush_percentage, 0.01);
        $this->assertEqualsWithDelta(1000.00, (float) $quotation->total, 0.01);
    }

    public function test_no_deadline_means_no_rush_fee(): void
    {
        $this->user();
        $this->rushTier(7, 25);
        $product = $this->product('OPEN', bulk: 50, retail: 100);

        $this->post('/quotations', [
            'customer_name' => 'Whenever',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::sole();
        $this->assertNull($quotation->deadline);
        $this->assertEqualsWithDelta(0.0, (float) $quotation->rush_percentage, 0.01);
        $this->assertEqualsWithDelta(100.00, (float) $quotation->total, 0.01);
    }

    public function test_the_tightest_tier_the_deadline_falls_inside_is_the_one_charged(): void
    {
        $this->user();
        $this->rushTier(2, 50);
        $this->rushTier(7, 25);
        $product = $this->product('TIER', bulk: 50, retail: 100);

        // Two days out is inside both tiers; the tighter one wins.
        $this->post('/quotations', [
            'customer_name' => 'Tomorrow please',
            'deadline' => today()->addDays(2)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(50.0, (float) Quotation::sole()->rush_percentage, 0.01);
    }

    public function test_a_deadline_of_today_is_as_urgent_as_it_gets(): void
    {
        $this->user();
        $this->rushTier(1, 60);
        $product = $this->product('TODAY', bulk: 50, retail: 100);

        $this->post('/quotations', [
            'customer_name' => 'Now',
            'deadline' => today()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(60.0, (float) Quotation::sole()->rush_percentage, 0.01);
        $this->assertEqualsWithDelta(160.00, (float) Quotation::sole()->total, 0.01);
    }

    public function test_a_deadline_already_past_is_charged_rather_than_falling_out_of_every_tier(): void
    {
        $this->user();
        $this->rushTier(2, 50);
        $product = $this->product('LATE', bulk: 50, retail: 100);

        $this->post('/quotations', [
            'customer_name' => 'Overdue',
            'deadline' => today()->subDays(5)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(50.0, (float) Quotation::sole()->rush_percentage, 0.01);
    }

    public function test_the_rush_fee_is_charged_after_the_volume_discount(): void
    {
        $this->user();
        Setting::create(['key' => 'default_margin', 'value' => '50']);
        QuantityBreak::create(['min_quantity' => 100, 'discount_percentage' => 20]);
        $this->rushTier(7, 25);
        $product = $this->product('BOTH', bulk: 10, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Big and urgent',
            'deadline' => today()->addDays(2)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 100]],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::sole();

        // List 100, less 20% is 80 a piece, so 8,000 - then a quarter on top.
        $this->assertEqualsWithDelta(8000.00, (float) $quotation->subtotal, 0.01);
        $this->assertEqualsWithDelta(2000.00, (float) $quotation->rush_amount, 0.01);
        $this->assertEqualsWithDelta(10000.00, (float) $quotation->total, 0.01);
    }

    public function test_the_rush_fee_is_kept_when_the_tiers_change_afterwards(): void
    {
        $this->user();
        $this->rushTier(7, 25);
        $product = $this->product('KEPT', bulk: 50, retail: 100);

        $this->post('/quotations', [
            'customer_name' => 'Quoted once',
            'deadline' => today()->addDays(3)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        RushTier::query()->delete();

        $quotation = Quotation::sole()->fresh();
        $this->assertEqualsWithDelta(25.0, (float) $quotation->rush_percentage, 0.01);
        $this->assertEqualsWithDelta(125.00, (float) $quotation->total, 0.01);
    }

    public function test_editing_a_quotation_reprices_its_rush_fee(): void
    {
        $this->user();
        $this->rushTier(7, 25);
        $product = $this->product('MOVED', bulk: 50, retail: 100);

        $this->post('/quotations', [
            'customer_name' => 'Moved the date',
            'deadline' => today()->addDays(2)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::sole();
        $this->assertEqualsWithDelta(125.00, (float) $quotation->total, 0.01);

        // The customer relaxes the deadline, so the fee comes off.
        $this->put("/quotations/{$quotation->id}", [
            'customer_name' => 'Moved the date',
            'deadline' => today()->addDays(60)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $quotation = $quotation->fresh();
        $this->assertEqualsWithDelta(0.0, (float) $quotation->rush_percentage, 0.01);
        $this->assertEqualsWithDelta(100.00, (float) $quotation->total, 0.01);
    }

    public function test_rush_tiers_are_saved_from_the_settings_form(): void
    {
        $this->user();

        $this->put('/admin/settings', $this->settingsPayload([
            'rush' => [
                ['within_days' => 2, 'surcharge_percentage' => 50],
                ['within_days' => 7, 'surcharge_percentage' => 25],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, RushTier::count());
        $this->assertEqualsWithDelta(
            50.0,
            (float) RushTier::where('within_days', 2)->sole()->surcharge_percentage,
            0.01
        );
    }

    public function test_a_rush_tier_cleared_on_the_form_stops_charging(): void
    {
        $this->user();
        $this->rushTier(7, 25);

        $this->put('/admin/settings', $this->settingsPayload([
            'rush' => [['within_days' => null, 'surcharge_percentage' => 25]],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(0, RushTier::count());
        $this->assertEqualsWithDelta(0.0, RushTier::surchargeFor(1), 0.01);
    }

    public function test_the_quotation_shows_what_the_deadline_cost(): void
    {
        $this->user();
        $this->rushTier(7, 25);
        $product = $this->product('SHOWN', bulk: 50, retail: 100);

        $this->post('/quotations', [
            'customer_name' => 'Visible',
            'deadline' => today()->addDays(3)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::sole();

        $this->get("/quotations/{$quotation->id}")
            ->assertOk()
            ->assertSee('Rush fee')
            ->assertSee('250.00')
            ->assertSee('1,250.00');
    }

    public function test_a_line_pays_only_for_the_stages_its_print_type_runs(): void
    {
        $this->user();
        // Sublimation runs laser cutting and the roller press; DTF runs manual
        // cutting and no press at all.
        $this->stageRate('cutting_laser', 12);
        $this->stageRate('cutting_manual', 4);
        $this->stageRate('press_roller', 9);
        $this->stageRate('press_small', 7);
        $product = $this->product('ROUTE', bulk: 50, retail: 50, printType: 'full_sublimation');

        $this->post('/quotations', [
            'customer_name' => 'Sublimated',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        // 50 of materials, 12 of laser cutting, 9 of roller press.
        $this->assertEqualsWithDelta(71.00, (float) Quotation::sole()->items->sole()->unit_price, 0.01);
    }

    public function test_a_dtf_line_pays_manual_cutting_and_no_press(): void
    {
        $this->user();
        $this->stageRate('cutting_laser', 12);
        $this->stageRate('cutting_manual', 4);
        $this->stageRate('press_roller', 9);
        $product = $this->product('DTFROUTE', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'DTF',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        // 50 of materials and 4 of manual cutting - no press on this route.
        $this->assertEqualsWithDelta(54.00, (float) Quotation::sole()->items->sole()->unit_price, 0.01);
    }

    public function test_a_silkscreen_line_pays_the_small_press(): void
    {
        $this->user();
        $this->stageRate('cutting_manual', 4);
        $this->stageRate('press_small', 7);
        $this->stageRate('press_roller', 9);
        $product = $this->product('SILK', bulk: 50, retail: 50, printType: 'silkscreen');

        $this->post('/quotations', [
            'customer_name' => 'Silkscreen',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(61.00, (float) Quotation::sole()->items->sole()->unit_price, 0.01);
    }

    public function test_an_embroidery_line_pays_the_embroidery_station(): void
    {
        $this->user();
        $this->stageRate('cutting_manual', 4);
        $this->stageRate('embroidery', 30);
        $product = $this->product('EMB', bulk: 50, retail: 50, printType: 'embroidery');

        $this->post('/quotations', [
            'customer_name' => 'Embroidered',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(84.00, (float) Quotation::sole()->items->sole()->unit_price, 0.01);
    }

    public function test_a_product_with_no_print_type_cannot_be_quoted(): void
    {
        $this->user();
        $product = $this->product('NOROUTE', bulk: 50, retail: 50);
        $product->forceFill(['print_type' => null])->save();

        // A product nobody has said how to make cannot be costed: there is no
        // telling which stages of the floor it will occupy.
        $this->post('/quotations', [
            'customer_name' => 'Undecided',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Quotation::count());
    }

    public function test_a_product_with_no_print_type_is_not_offered_for_quoting(): void
    {
        $this->user();
        $product = $this->product('HIDDEN', bulk: 50, retail: 50);
        $product->forceFill(['print_type' => null])->save();

        $this->get('/quotations/create')->assertOk()->assertDontSee('HIDDEN');
    }

    public function test_a_route_that_is_not_on_the_floor_is_refused(): void
    {
        $this->user();
        $category = ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel']);

        // The floor runs the print types the production system knows about,
        // and nothing else.
        $this->post('/admin/products', [
            'sku' => 'BADROUTE', 'name' => 'Bad route',
            'product_category_id' => $category->id,
            'print_type' => 'hand_painted',
        ])->assertSessionHasErrors('print_type');
    }

    public function test_the_once_per_job_stages_are_charged_once_as_setup(): void
    {
        $this->user();
        $this->stageRate('layout', 200);
        $this->stageRate('mockup', 150);
        $this->stageRate('sample', 100);
        $this->stageRate('release', 50);
        $this->stageRate('sewing', 10);
        $product = $this->product('SETUP', bulk: 40, retail: 40);

        $this->post('/quotations', [
            'customer_name' => 'One design',
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::sole();

        // Ten pieces at 50 each is 500, plus 500 of setup charged once.
        $this->assertEqualsWithDelta(500.00, (float) $quotation->setup_cost, 0.01);
        $this->assertEqualsWithDelta(1000.00, (float) $quotation->subtotal, 0.01);
        $this->assertEqualsWithDelta(1000.00, (float) $quotation->total, 0.01);
    }

    public function test_setup_makes_a_small_run_dearer_per_piece_than_a_large_one(): void
    {
        $this->user();
        $this->stageRate('layout', 500);
        $product = $this->product('RUNSIZE', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Ten pieces',
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ])->assertSessionHasNoErrors();

        $small = Quotation::latest('id')->first();
        $smallEach = (float) $small->total / 10;

        $this->post('/quotations', [
            'customer_name' => 'A hundred pieces',
            'items' => [['product_id' => $product->id, 'quantity' => 100]],
        ])->assertSessionHasNoErrors();

        $large = Quotation::latest('id')->first();
        $largeEach = (float) $large->total / 100;

        // The same 500 of layout spread over ten pieces or a hundred.
        $this->assertEqualsWithDelta(100.00, $smallEach, 0.01);
        $this->assertEqualsWithDelta(55.00, $largeEach, 0.01);
        $this->assertGreaterThan($largeEach, $smallEach);
    }

    public function test_two_lines_do_not_buy_two_layouts(): void
    {
        $this->user();
        $this->stageRate('layout', 300);
        $tee = $this->product('MULTI-TEE', bulk: 50, retail: 50);
        $cap = $this->product('MULTI-CAP', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Two products',
            'items' => [
                ['product_id' => $tee->id, 'quantity' => 1],
                ['product_id' => $cap->id, 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        // One quotation, one layout, whatever routes it mixes.
        $this->assertEqualsWithDelta(300.00, (float) Quotation::sole()->setup_cost, 0.01);
    }

    public function test_setup_carries_the_margin_like_everything_else(): void
    {
        $this->user();
        Setting::create(['key' => 'default_margin', 'value' => '50']);
        $this->stageRate('layout', 100);
        $product = $this->product('SETUPMARGIN', bulk: 25, retail: 25);

        $this->post('/quotations', [
            'customer_name' => 'Marked up',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::sole();

        // 100 of setup at a 50% margin sells at 200, like the 25 sells at 50.
        $this->assertEqualsWithDelta(200.00, (float) $quotation->setup_cost, 0.01);
        $this->assertEqualsWithDelta(250.00, (float) $quotation->total, 0.01);
    }

    public function test_the_rush_fee_is_charged_on_the_setup_too(): void
    {
        $this->user();
        $this->rushTier(7, 25);
        $this->stageRate('layout', 100);
        $product = $this->product('RUSHSETUP', bulk: 100, retail: 100);

        $this->post('/quotations', [
            'customer_name' => 'Urgent',
            'deadline' => today()->addDays(2)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::sole();

        // A rushed job rushes its layout too: 200 subtotal, a quarter on top.
        $this->assertEqualsWithDelta(200.00, (float) $quotation->subtotal, 0.01);
        $this->assertEqualsWithDelta(50.00, (float) $quotation->rush_amount, 0.01);
        $this->assertEqualsWithDelta(250.00, (float) $quotation->total, 0.01);
    }

    public function test_a_line_snapshots_the_route_it_was_quoted_on(): void
    {
        $this->user();
        $this->stageRate('cutting_laser', 12);
        $this->stageRate('cutting_manual', 4);
        $product = $this->product('EDITROUTE', bulk: 50, retail: 50, printType: 'full_sublimation');

        $this->post('/quotations', [
            'customer_name' => 'Routed',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::sole();
        $this->assertSame('full_sublimation', $quotation->items->sole()->print_type);

        // Re-routing the product afterwards must not rewrite what was quoted.
        $product->forceFill(['print_type' => 'dtf'])->save();
        $this->assertSame('full_sublimation', $quotation->fresh('items')->items->sole()->print_type);
    }

    public function test_duplicating_keeps_the_route(): void
    {
        $this->user();
        $product = $this->product('DUPROUTE', bulk: 50, retail: 50, printType: 'silkscreen');

        $this->post('/quotations', [
            'customer_name' => 'Repeat',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $original = Quotation::sole();
        $this->post("/quotations/{$original->id}/duplicate")->assertSessionHasNoErrors();

        $copy = Quotation::with('items')->latest('id')->first();
        $this->assertSame('silkscreen', $copy->items->sole()->print_type);
    }

    public function test_stage_rates_are_saved_from_the_settings_form(): void
    {
        $this->user();

        $this->put('/admin/settings', $this->settingsPayload([
            'stages' => ['layout' => 250, 'sewing' => 12.5],
        ]))->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(250.0, (float) ProductionStage::where('key', 'layout')->sole()->rate, 0.01);
        $this->assertEqualsWithDelta(12.5, (float) ProductionStage::where('key', 'sewing')->sole()->rate, 0.01);
    }

    public function test_the_quotation_shows_the_setup_it_charged(): void
    {
        $this->user();
        $this->stageRate('layout', 300);
        $product = $this->product('SHOWSETUP', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Visible',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->get('/quotations/'.Quotation::sole()->id)
            ->assertOk()
            ->assertSee('Setup')
            ->assertSee('DTF')
            ->assertSee('300.00');
    }

    public function test_the_picker_names_each_product_and_its_route(): void
    {
        $this->user();
        $this->product('PICK-TEE', bulk: 50, retail: 50, printType: 'full_sublimation');

        $this->get('/quotations/create')
            ->assertOk()
            ->assertSee('PICK-TEE')
            ->assertSee('PICK-TEE product')
            ->assertSee('Full Sublimation');
    }

    public function test_the_summary_totals_do_not_share_a_hook_with_the_product_options(): void
    {
        $this->user();
        $this->stageRate('layout', 300);
        $this->product('HOOK-TEE', bulk: 50, retail: 50);

        // The options carry each product's stage figures and the summary
        // carries the quotation's. Sharing an attribute made the browser write
        // the setup total into the first product's name.
        $page = $this->get('/quotations/create')->assertOk()->getContent();

        $this->assertStringContainsString('data-stage-setup=', $page);
        $this->assertStringContainsString('data-setup-total', $page);
        $this->assertStringNotContainsString('data-setup="', $page);
    }

    public function test_a_quotation_stands_for_the_validity_days_in_settings(): void
    {
        $this->user();
        Setting::updateOrCreate(['key' => 'quotation_validity_days'], ['value' => '15']);
        $product = $this->product('VALID', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        // Nobody types an expiry: the shop says how long a price stands.
        $this->assertSame(
            today()->addDays(15)->toDateString(),
            Quotation::sole()->valid_until->toDateString()
        );
    }

    public function test_the_validity_follows_the_setting(): void
    {
        $this->user();
        Setting::updateOrCreate(['key' => 'quotation_validity_days'], ['value' => '30']);
        $product = $this->product('VALID30', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            today()->addDays(30)->toDateString(),
            Quotation::sole()->valid_until->toDateString()
        );
    }

    public function test_a_validity_of_nothing_leaves_the_quotation_without_an_expiry(): void
    {
        $this->user();
        Setting::updateOrCreate(['key' => 'quotation_validity_days'], ['value' => '0']);
        $product = $this->product('NOEXPIRY', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        // Expiring the moment it is written is nobody's intention.
        $this->assertNull(Quotation::sole()->valid_until);
    }

    public function test_the_form_never_accepts_an_expiry_from_the_browser(): void
    {
        $this->user();
        Setting::updateOrCreate(['key' => 'quotation_validity_days'], ['value' => '15']);
        $product = $this->product('FORGED', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Chancer',
            'valid_until' => today()->addYears(5)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            today()->addDays(15)->toDateString(),
            Quotation::sole()->valid_until->toDateString()
        );
    }

    public function test_re_quoting_restarts_the_clock(): void
    {
        $this->user();
        Setting::updateOrCreate(['key' => 'quotation_validity_days'], ['value' => '15']);
        $product = $this->product('RECLOCK', bulk: 50, retail: 50);

        $this->post('/quotations', [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $quotation = Quotation::sole();
        $quotation->forceFill(['valid_until' => today()->subDays(3)])->save();

        // An edit re-prices at today's costs, so the price stands from today.
        $this->put("/quotations/{$quotation->id}", [
            'customer_name' => 'Buyer',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            today()->addDays(15)->toDateString(),
            $quotation->fresh()->valid_until->toDateString()
        );
    }

    public function test_the_form_says_how_long_the_price_will_stand(): void
    {
        $this->user();
        Setting::updateOrCreate(['key' => 'quotation_validity_days'], ['value' => '15']);
        $this->product('SAYS', bulk: 50, retail: 50);

        $this->get('/quotations/create')
            ->assertOk()
            ->assertSee('This price stands for 15 days from today.');
    }
}
