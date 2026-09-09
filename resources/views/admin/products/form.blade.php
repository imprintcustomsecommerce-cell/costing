@extends('layouts.app')

@section('content')
@php($recipe = $product->exists ? $product->materials->keyBy('id') : collect())
<div class="top">
    <div>
        <div class="page-kicker">Product catalog</div>
        <h1>{{ $product->exists ? $product->name : 'New product' }}</h1>
        <div class="muted">Pick the materials this product is made of and how much of each it uses. The cost follows.</div>
    </div>
    <a class="btn btn-secondary" href="{{ route('admin.products.index') }}">Back to products</a>
</div>

<form class="card" method="post" action="{{ $product->exists ? route('admin.products.update',$product) : route('admin.products.store') }}">
    @csrf
    @if($product->exists) @method('put') @endif
    @if($errors->any())<div class="alert-error">{{ $errors->first() }}</div>@endif

    <div class="form-grid form-grid-three">
        <label>SKU<input name="sku" value="{{ old('sku',$product->sku) }}" required></label>
        <label>Product name<input name="name" value="{{ old('name',$product->name) }}" required></label>
        <label>Category
            <select name="product_category_id" required>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('product_category_id',$product->product_category_id)==$category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="form-section">
        <div class="section-title-row">
            <div>
                <h3>Materials</h3>
                <p class="muted">Tick what this product is made of, then set how many of each it uses.</p>
            </div>
            <span class="selection-count" id="product-material-count">0 selected</span>
        </div>

        <div class="material-picker-tools">
            <label class="material-search">Search materials
                <input id="product-material-search" type="search" placeholder="Search by SKU or material name" autocomplete="off">
            </label>
            <button type="button" class="btn btn-secondary btn-small" id="product-material-selected">Show selected</button>
        </div>

        <div class="choice-grid" id="product-material-list">
            @foreach($materials as $material)
                @php($chosen = $recipe->get($material->id))
                <label class="choice-card material-line {{ $chosen ? 'is-picked' : '' }}"
                    data-material-search="{{ strtolower($material->sku.' '.$material->name) }}">
                    <input type="checkbox" name="materials[]" value="{{ $material->id }}"
                        @checked(in_array($material->id, old('materials', $recipe->keys()->all())))>
                    @php($perCm2 = $material->areaDivisorCm2())
                    <span class="material-line-name">{{ $material->name }}
                        <em>&#8369;{{ number_format((float) ($material->retail_cost ?? $material->current_cost), 2) }} / {{ str_replace('_', ' ', $material->unit) }}
                            @if((float) $material->waste_percentage > 0) &middot; +{{ rtrim(rtrim(number_format((float) $material->waste_percentage, 2), '0'), '.') }}% waste @endif</em>
                        @if($perCm2)<em class="material-line-tag">by artwork size</em>@endif
                    </span>
                    <input class="material-qty" type="number" step=".0001" min="0"
                        name="material_quantities[{{ $material->id }}]"
                        value="{{ old('material_quantities.'.$material->id, $chosen?->pivot?->quantity) ?: 1 }}"
                        data-qty
                        data-cost="{{ (float) ($material->retail_cost ?? $material->current_cost) }}"
                        data-waste="{{ (float) $material->waste_percentage }}"
                        data-per-cm2="{{ $perCm2 ?? '' }}"
                        aria-label="Quantity of {{ $material->name }}"
                        title="How many {{ str_replace('_', ' ', $material->unit) }} this product uses">
                </label>
            @endforeach
        </div>
    </div>

    @php($margin = \App\Models\Setting::margin())
    <div class="costing-summary" data-costing-summary data-margin="{{ $margin }}">
        <div><span>Material cost</span><b data-material-total>&#8369;0.00</b></div>
        <div data-area-row hidden><span>Per cm&sup2; of print</span><b data-area-total>&#8369;0.0000</b></div>
        <div><span>Estimated selling price <em>{{ rtrim(rtrim(number_format($margin, 2), '0'), '.') }}% margin</em></span><b data-selling-total>&#8369;0.00</b></div>
        <p class="muted" data-costing-note>Tick materials above and set how many of each this product uses.</p>
        <p class="muted" data-area-note hidden>A material measured by area costs nothing until an artwork size is given, so it is quoted per square centimetre here and priced on the quotation. Labour comes from the production stages this product's print type runs.</p>
    </div>

    <label class="field-block">Print type <span class="cost-helper-optional">how this product is made — decides which production stages it pays for. A product printed two ways needs an entry for each.</span>
        <select name="print_type">
            <option value="">— not set —</option>
            @foreach(\App\Support\ProductionPipeline::printTypes() as $key => $type)
                <option value="{{ $key }}" @selected(old('print_type',$product->print_type)===$key)>{{ $type['label'] }}</option>
            @endforeach
        </select>
    </label>

    <label class="field-block">Description<textarea name="description">{{ old('description',$product->description) }}</textarea></label>
    <input type="hidden" name="is_active" value="1">

    <div class="form-actions">
        <button class="btn">Save product</button>
        <a class="btn btn-secondary" href="{{ route('admin.products.index') }}">Cancel</a>
    </div>
</form>

<script>
/* The cost of a product is the sum of its materials. Recomputed as the recipe
   is edited so the figure is visible before saving; the server recomputes it
   from the same quantities afterwards, so this is a preview, never the value. */
document.addEventListener('DOMContentLoaded', () => {
    const summary = document.querySelector('[data-costing-summary]');
    if (!summary) return;

    const peso = value => '₱' + value.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const materialOut = summary.querySelector('[data-material-total]');
    const sellingOut = summary.querySelector('[data-selling-total]');
    const note = summary.querySelector('[data-costing-note]');
    const areaOut = summary.querySelector('[data-area-total]');
    const areaRow = summary.querySelector('[data-area-row]');
    const areaNote = summary.querySelector('[data-area-note]');
    const count = document.getElementById('product-material-count');

    function recalculate() {
        let total = 0, perCm2 = 0, picked = 0, areaCount = 0;

        for (const row of document.querySelectorAll('.material-line')) {
            const ticked = row.querySelector('input[type="checkbox"]');
            const qty = row.querySelector('[data-qty]');
            /* An unticked material is not part of the product, so its quantity
               is disabled rather than posted. */
            qty.disabled = !ticked.checked;
            row.classList.toggle('is-picked', ticked.checked);
            if (!ticked.checked) continue;

            const amount = parseFloat(qty.value) || 0;
            /* Waste is bought but not delivered, so it is charged on top. */
            const consumed = amount * (1 + (parseFloat(qty.dataset.waste || 0) / 100));
            const cost = parseFloat(qty.dataset.cost || 0);

            /* A material measured by area is consumed by how big the print is.
               How many square centimetres one unit of it covers is known; how
               big the artwork will be is not, and is not decided until a
               quotation. Charging it as a whole unit here would say a shirt
               eats a full square metre of film. It is quoted as a rate
               instead. */
            const divisor = parseFloat(qty.dataset.perCm2 || 0);
            if (divisor > 0) {
                perCm2 += (consumed / divisor) * cost;
                areaCount++;
            } else {
                total += consumed * cost;
            }
            picked++;
        }

        const margin = parseFloat(summary.dataset.margin || 0);
        const selling = margin >= 100 || margin <= 0 ? total : total / (1 - (margin / 100));

        materialOut.textContent = peso(total);
        sellingOut.textContent = peso(selling);
        areaOut.textContent = '₱' + perCm2.toLocaleString('en-PH', {minimumFractionDigits: 4, maximumFractionDigits: 4});
        areaRow.hidden = areaCount === 0;
        areaNote.hidden = areaCount === 0;

        note.textContent = picked
            ? picked + (picked === 1 ? ' material' : ' materials') + ' × quantity'
                + (areaCount ? ', ' + areaCount + ' sized by the artwork' : '')
            : 'Tick materials above and set how many of each this product uses.';
        if (count) count.textContent = picked + ' selected';
    }

    document.addEventListener('change', e => { if (e.target.matches('.material-line input')) recalculate(); });
    document.addEventListener('input', e => { if (e.target.matches('[data-qty]')) recalculate(); });

    /* Searching a long list, and narrowing it to what is already in the recipe,
       are the two ways to find anything among hundreds of materials. */
    const search = document.getElementById('product-material-search');
    const onlySelected = document.getElementById('product-material-selected');
    let selectedOnly = false;

    function filter() {
        const term = (search?.value || '').trim().toLowerCase();
        for (const row of document.querySelectorAll('.material-line')) {
            const matches = !term || row.dataset.materialSearch.includes(term);
            const included = !selectedOnly || row.querySelector('input[type="checkbox"]').checked;
            row.hidden = !(matches && included);
        }
    }

    search?.addEventListener('input', filter);
    onlySelected?.addEventListener('click', () => {
        selectedOnly = !selectedOnly;
        onlySelected.textContent = selectedOnly ? 'Show all' : 'Show selected';
        filter();
    });

    recalculate();
});
</script>
@endsection
