<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Services\AuditService;
use App\Services\MaterialCostService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MaterialController extends Controller
{
    public function index(Request $r)
    {
        return view('admin.materials.index', ['materials' => Material::with('category')->when($r->q, fn ($q, $v) => $q->where(fn ($x) => $x->where('sku', 'like', "%{$v}%")->orWhere('name', 'like', "%{$v}%")))->orderBy('name')->paginate(25)]);
    }

    public function create()
    {
        return view('admin.materials.form', ['material' => new Material, 'categories' => MaterialCategory::active()->get()]);
    }

    public function store(Request $r, MaterialCostService $costs, AuditService $audit)
    {
        $data = $this->validated($r);
        $initial = $this->unitCost($data);
        $priceType = $data['price_type'] ?? (filled($data['retail_cost'] ?? null) ? 'retail' : 'bulk');
        $data['retail_cost'] = $priceType === 'retail' ? $initial : null;
        unset($data['cost'], $data['price_type'], $data['bulk_total'], $data['bulk_quantity'], $data['effective_from'], $data['reason']);
        $material = Material::create($data);
        $costs->change($material, $initial, $r->effective_from, $r->reason);
        $audit->log('created', $material, [], $material->toArray());

        return redirect()->route('admin.materials.edit', $material)->with('success', 'Material created.');
    }

    public function edit(Material $material)
    {
        return view('admin.materials.form', ['material' => $material->load('costHistories.changedBy'), 'categories' => MaterialCategory::active()->get()]);
    }

    public function update(Request $r, Material $material, AuditService $audit)
    {
        $data = $this->validated($r, false);
        unset($data['cost'], $data['bulk_total'], $data['bulk_quantity'], $data['effective_from'], $data['reason']);
        $old = $material->toArray();
        $material->update($data);
        $audit->log('material_configuration_changed', $material, $old, $material->fresh()->toArray());

        return back()->with('success', 'Material updated.');
    }

    public function cost(Request $r, Material $material, MaterialCostService $service)
    {
        $data = $r->validate([
            'price_type' => 'nullable|in:retail,bulk',
            'retail_cost' => 'nullable|numeric|min:0.0001',
            'bulk_total' => 'nullable|numeric|min:0.0001|required_with:bulk_quantity',
            'bulk_quantity' => 'nullable|numeric|min:0.0001|required_with:bulk_total',
            // Kept for older integrations, but the form calculates it from the
            // purchase total and quantity instead of trusting a typed unit cost.
            'cost' => 'nullable|numeric|min:0.0001',
            'effective_from' => 'required|date',
            'reason' => 'required|string|max:255',
        ]);
        $unitCost = $this->unitCost($data);
        $service->change($material, $unitCost, $data['effective_from'], $data['reason']);
        $material->update(['retail_cost' => ($data['price_type'] ?? 'bulk') === 'retail' ? $unitCost : null]);

        return back()->with('success', 'New cost recorded without overwriting history.');
    }

    private function validated(Request $r, bool $create = true): array
    {
        return $r->validate(['sku' => ['required', 'string', 'max:50', Rule::unique('materials')->ignore($r->route('material'))], 'name' => 'required|string|max:255', 'material_category_id' => 'nullable|exists:material_categories,id', 'unit' => 'required|in:'.implode(',', Material::UNITS), 'width_cm' => 'nullable|numeric|min:0.0001', 'length_cm' => 'nullable|numeric|min:0.0001', 'coverage_per_sqm' => 'nullable|numeric|min:0.0001', 'supplier' => 'nullable|string|max:255', 'waste_percentage' => 'required|numeric|min:0|max:100', 'price_type' => 'nullable|in:retail,bulk', 'retail_cost' => 'nullable|numeric|min:0.0001', 'notes' => 'nullable|string', 'is_active' => 'nullable|boolean', 'bulk_total' => 'nullable|numeric|min:0.0001|required_with:bulk_quantity', 'bulk_quantity' => 'nullable|numeric|min:0.0001|required_with:bulk_total', 'cost' => 'nullable|numeric|min:0.0001', 'effective_from' => ($create ? 'required' : 'nullable').'|date', 'reason' => ($create ? 'required' : 'nullable').'|string|max:255']);
    }

    /** Resolve the one material price from the selected purchase type. */
    private function unitCost(array $data): float
    {
        if (($data['price_type'] ?? null) === 'retail') {
            if (filled($data['retail_cost'] ?? null)) {
                return (float) $data['retail_cost'];
            }

            throw ValidationException::withMessages(['retail_cost' => 'Enter the retail price per unit.']);
        }

        if (filled($data['bulk_total'] ?? null) && filled($data['bulk_quantity'] ?? null)) {
            return round((float) $data['bulk_total'] / (float) $data['bulk_quantity'], 4);
        }

        if (filled($data['cost'] ?? null)) {
            return (float) $data['cost'];
        }

        throw ValidationException::withMessages([
            'bulk_total' => 'Enter the bulk total price and quantity received.',
        ]);
    }
}
