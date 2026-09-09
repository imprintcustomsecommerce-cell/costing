<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\ProductionPipeline;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A product is a list of materials and how much of each it uses. Its cost is
 * the sum of them, at either the bulk or the retail material price.
 */
class ProductController extends Controller
{
    public function index(Request $r)
    {
        return view('admin.products.index', [
            'products' => Product::with('category', 'materials')
                ->when($r->q, fn ($query, $term) => $query->where(fn ($x) => $x
                    ->where('sku', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function create()
    {
        return $this->form(new Product);
    }

    public function edit(Product $product)
    {
        return $this->form($product->load('materials'));
    }

    public function store(Request $r, AuditService $audit)
    {
        $data = $this->validated($r);
        $quantities = $this->takeRecipe($data);
        $product = Product::create($data);
        $this->syncRecipe($product, $quantities);
        $audit->log('created', $product, [], $this->snapshot($product));

        return redirect()->route('admin.products.edit', $product)->with('success', 'Product created.');
    }

    public function update(Request $r, Product $product, AuditService $audit)
    {
        $before = $this->snapshot($product);
        $data = $this->validated($r, $product);
        $quantities = $this->takeRecipe($data);
        $product->update($data);
        $this->syncRecipe($product, $quantities);
        $audit->log('product_changed', $product, $before, $this->snapshot($product->fresh('materials')));

        return back()->with('success', 'Product updated.');
    }

    private function form(Product $product)
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => ProductCategory::active()->orderBy('name')->get(),
            'materials' => Material::active()->orderBy('name')->get(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $r, ?Product $product = null): array
    {
        return $r->validate([
            'sku' => ['required', 'string', 'max:50', Rule::unique('products')->ignore($product)],
            'name' => 'required|string|max:255',
            'product_category_id' => 'required|exists:product_categories,id',
            'description' => 'nullable|string',
            'print_type' => ['nullable', Rule::in(ProductionPipeline::keys())],
            'is_active' => 'nullable|boolean',
            'materials' => 'nullable|array',
            'materials.*' => 'exists:materials,id',
            'material_quantities' => 'nullable|array',
            'material_quantities.*' => 'nullable|numeric|min:0',
        ]);
    }

    /**
     * Lift the recipe out of the validated input so what remains is columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, float>  material id => quantity
     */
    private function takeRecipe(array &$data): array
    {
        $chosen = $data['materials'] ?? [];
        $quantities = $data['material_quantities'] ?? [];
        unset($data['materials'], $data['material_quantities']);

        // A blank or zero quantity means one. Zero would make the material
        // free, which is never what leaving a box empty was meant to say.
        return collect($chosen)
            ->mapWithKeys(fn ($id) => [(int) $id => (float) ($quantities[$id] ?? 0) ?: 1.0])
            ->all();
    }

    /** @param  array<int, float>  $quantities */
    private function syncRecipe(Product $product, array $quantities): void
    {
        $product->materials()->sync(
            collect($quantities)->map(fn (float $quantity) => ['quantity' => $quantity])->all()
        );
    }

    /** @return array<string, mixed> */
    private function snapshot(Product $product): array
    {
        $product->loadMissing('materials');
        $costing = $product->costing();

        return $product->only(['sku', 'name', 'product_category_id', 'is_active', 'print_type']) + [
            'materials' => $product->materials
                ->mapWithKeys(fn (Material $m) => [$m->sku => (float) $m->pivot->quantity])
                ->all(),
            'bulk_cost' => $costing['bulk'],
            'retail_cost' => $costing['retail'],
        ];
    }
}
