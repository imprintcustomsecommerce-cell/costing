<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\MaterialCostHistory;
use App\Models\Product;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $products = Product::with('materials')->where('is_active', true)->get();

        // A product with no materials costs nothing, which is almost never true
        // and is the single most likely reason a figure looks wrong.
        $unpriced = $products->filter(fn (Product $product) => $product->materials->isEmpty());

        // A material with no cost silently contributes zero to every product
        // that uses it, so it is worth surfacing rather than discovering later.
        $costless = Material::where('is_active', true)
            ->where(fn ($query) => $query->whereNull('current_cost')->orWhere('current_cost', '<=', 0))
            ->orderBy('name')
            ->get();

        $costed = $products->reject(fn (Product $product) => $product->materials->isEmpty())
            ->map(fn (Product $product) => $product->costing()['bulk']);

        return view('dashboard', [
            'productCount' => $products->count(),
            'materialCount' => Material::where('is_active', true)->count(),
            'unpriced' => $unpriced,
            'costless' => $costless,
            'averageCost' => $costed->isNotEmpty() ? $costed->avg() : 0.0,
            'recentProducts' => Product::with('category', 'materials')->latest('id')->limit(8)->get(),
            'costAlerts' => MaterialCostHistory::with('material')
                ->whereNotNull('change_percentage')
                ->where(fn ($query) => $query->where('change_percentage', '>=', 5)->orWhere('change_percentage', '<=', -5))
                ->latest('effective_from')
                ->latest('id')
                ->limit(8)
                ->get(),
        ]);
    }
}
