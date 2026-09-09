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
 * Every screen renders while signed in.
 *
 * An unauthenticated request only ever redirects, so a route smoke test says
 * nothing about whether the page behind it works. The product list referenced a
 * relation that had been deleted and 500'd for exactly this reason: it looked
 * fine from outside the login.
 */
class ScreensLoadTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): void
    {
        $materialCategory = MaterialCategory::firstOrCreate(['name' => 'Fabric'], ['is_active' => true]);
        $material = Material::create([
            'sku' => 'BLANK', 'name' => 'Blank', 'material_category_id' => $materialCategory->id,
            'unit' => 'piece', 'retail_cost' => 80, 'is_active' => true,
        ]);
        $material->forceFill(['current_cost' => 65])->save();
        $material->costHistories()->create([
            'cost' => 65, 'previous_cost' => 50, 'change_percentage' => 30,
            'effective_from' => today(), 'reason' => 'Opening cost',
        ]);

        $product = Product::create([
            'sku' => 'TEE', 'name' => 'Tee',
            'product_category_id' => ProductCategory::firstOrCreate(['slug' => 'apparel'], ['name' => 'Apparel'])->id,
            'is_active' => true,
        ]);
        $product->materials()->sync([$material->id => ['quantity' => 2]]);

        // A product with nothing in it: the dashboard calls this out, and the
        // costing must cope with an empty recipe rather than divide by nothing.
        Product::create([
            'sku' => 'EMPTY', 'name' => 'Empty',
            'product_category_id' => ProductCategory::first()->id, 'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'SUPER ADMIN', 'guard_name' => 'web']);
        foreach (['manage users', 'manage products', 'manage materials', 'view internal costs', 'view audit logs'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_every_screen_renders_for_an_administrator(): void
    {
        $this->seedCatalog();
        $this->actingAs($this->admin());

        $product = Product::where('sku', 'TEE')->sole();
        $material = Material::where('sku', 'BLANK')->sole();

        $screens = [
            '/dashboard',
            '/admin/products',
            '/admin/products/create',
            '/admin/products/'.$product->id.'/edit',
            '/admin/materials',
            '/admin/materials/create',
            '/admin/materials/'.$material->id.'/edit',
            '/admin/users',
            '/admin/users/create',
            '/admin/settings',
            '/admin/audit-logs',
        ];

        foreach ($screens as $screen) {
            $this->get($screen)->assertOk();
        }
    }

    public function test_the_product_list_shows_what_each_product_costs(): void
    {
        $this->seedCatalog();
        $this->actingAs($this->admin());

        $this->get('/admin/products')
            ->assertOk()
            ->assertSee('Tee')
            // The list shows what the product costs to make: 2 x P80.
            ->assertSee('160.00');
    }

    public function test_the_dashboard_flags_what_costs_nothing(): void
    {
        $this->seedCatalog();
        $this->actingAs($this->admin());

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Needs attention')
            ->assertSee('Empty');
    }

    public function test_the_removed_pricing_screens_are_gone(): void
    {
        $this->actingAs($this->admin());

        // Quotations, artwork and customers came back over the simple costing
        // model; the print-pricing engine did not.
        foreach (['/admin/pricing', '/admin/pricing/advanced'] as $screen) {
            $this->get($screen)->assertNotFound();
        }
    }

    /**
     * No Blade directive leaks to the page as text.
     *
     * Blade only compiles a directive when the @ is not preceded by a word
     * character, so "cm@endif" or "@endif@if" stays as literal text. One left
     * an @if unclosed and broke the PDF; another printed itself into the
     * quotation header. Neither is visible to an assertion that only checks
     * the page loaded.
     */
    public function test_no_view_prints_an_uncompiled_blade_directive(): void
    {
        $this->seedCatalog();
        $this->actingAs($this->admin());

        $product = Product::where('sku', 'TEE')->sole();
        $material = Material::where('sku', 'BLANK')->sole();

        $screens = [
            '/dashboard', '/admin/products', '/admin/products/create',
            '/admin/products/'.$product->id.'/edit', '/admin/materials',
            '/admin/materials/create', '/admin/materials/'.$material->id.'/edit',
            '/quotations', '/quotations/create', '/customers', '/customers/create',
            '/artwork', '/admin/users', '/admin/settings', '/admin/audit-logs',
        ];

        foreach ($screens as $screen) {
            $html = $this->get($screen)->assertOk()->getContent();

            $this->assertDoesNotMatchRegularExpression(
                '/@(if|endif|else|foreach|endforeach|unless|endunless|isset|endisset|checked|selected)/',
                $html,
                "{$screen} rendered an uncompiled Blade directive as text",
            );
        }
    }
}
