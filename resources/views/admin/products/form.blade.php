@extends('layouts.app')

@section('content')
@php($recipe = $product->exists ? $product->materials->keyBy('id') : collect())
@php($selectedMaterials = old('materials', $recipe->keys()->all()))
@php($preferredMaterialGroups = ['Fabric', 'Accessories', 'Ribbings'])
@php($materialGroups = $materials->toBase()->groupBy(fn ($material) => $material->category?->name ?: 'Other materials'))
<div class="top">
    <div>
        <div class="page-kicker">Product catalog</div>
        <h1>{{ $product->exists ? $product->name : 'New product' }}</h1>
        <div class="muted">Build the full unit cost in order: materials, printing, labor, then packaging.</div>
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

    <div class="cost-step">
        <div class="cost-step-number">1</div>
        <div class="cost-step-body">
            <div class="section-title-row">
                <div>
                    <h3>Materials</h3>
                    <p class="muted">Choose the fabric, accessories, ribbings, and any other materials needed for one finished piece. At least one ribbing is required.</p>
                </div>
                <span class="selection-count" id="product-material-count">0 selected</span>
            </div>

            <div class="material-picker-tools">
                <label class="material-search">Search materials
                    <input id="product-material-search" type="search" placeholder="Search by SKU or material name" autocomplete="off">
                </label>
                <button type="button" class="btn btn-secondary btn-small" id="product-material-selected">Show selected</button>
            </div>

            <div id="product-material-list">
                @foreach($preferredMaterialGroups as $groupName)
                    @if($materialGroups->has($groupName))
                        <section class="material-group" data-material-group>
                            <div class="material-group-title">{{ $groupName }}@if($groupName === 'Ribbings') <span class="field-required">required</span>@endif</div>
                            @if($groupName === 'Ribbings')
                                <p class="muted" style="margin:0 0 10px;font-size:12px">A garment is costed with its collar, cuffs or waistband. Pick at least one.</p>
                            @endif
                            <div class="choice-grid">
                                @foreach($materialGroups->get($groupName) as $material)
                                    @include('admin.products.material-line', ['material' => $material, 'recipe' => $recipe, 'selectedMaterials' => $selectedMaterials])
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach

                @foreach($materialGroups->except($preferredMaterialGroups) as $groupName => $groupMaterials)
                    <section class="material-group" data-material-group>
                        <div class="material-group-title">{{ $groupName }}</div>
                        <div class="choice-grid">
                            @foreach($groupMaterials as $material)
                                @include('admin.products.material-line', ['material' => $material, 'recipe' => $recipe, 'selectedMaterials' => $selectedMaterials])
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>

    <div class="cost-step">
        <div class="cost-step-number">2</div>
        <div class="cost-step-body">
            <div class="section-title-row">
                <div>
                    <h3>Printing</h3>
                    <p class="muted">Select the print method and enter its cost per piece. Silkscreen, embroidery, and full sublimation are supported together with your existing methods.</p>
                </div>
            </div>
            <div class="form-grid form-grid-three cost-input-grid">
                <label>Printing method
                    <select name="print_type" required>
                        <option value="" disabled @selected(!old('print_type', $product->print_type))>— select printing method —</option>
                        @foreach(\App\Support\ProductionPipeline::printTypes() as $key => $type)
                            <option value="{{ $key }}" @selected(old('print_type',$product->print_type)===$key)>{{ $type['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Printing cost / piece <span class="cost-helper-optional" data-base-hint>base price</span>
                    <span class="money-input"><span>₱</span><input name="printing_cost" type="number" step=".01" min="0.01" required value="{{ old('printing_cost', $product->printing_cost) }}" data-component="printing" data-printing-base></span>
                </label>

                {{-- Silkscreen is a screen per design: the base covers the
                     first, each one after it adds the extra charge. --}}
                <label data-method-field="silkscreen" hidden>Number of designs
                    <input name="design_count" type="number" step="1" min="1" max="50" value="{{ old('design_count', $product->design_count ?? 1) }}" data-design-count>
                </label>
                <label data-method-field="silkscreen" hidden>Cost per extra design
                    <span class="money-input"><span>₱</span><input name="extra_design_cost" type="number" step=".01" min="0" value="{{ old('extra_design_cost', $product->extra_design_cost ?? 0) }}" data-extra-design></span>
                </label>

                {{-- Embroidery is bought by the stitch. --}}
                <label data-method-field="embroidery" hidden>Stitch count
                    <input name="stitch_count" type="number" step="1" min="1" max="5000000" value="{{ old('stitch_count', $product->stitch_count ?? 0) }}" data-stitch-count>
                </label>
                <label data-method-field="embroidery" hidden>Cost per stitch
                    <span class="money-input"><span>₱</span><input name="cost_per_stitch" type="number" step=".000001" min="0" value="{{ old('cost_per_stitch', $product->cost_per_stitch ?? 0) }}" data-cost-per-stitch></span>
                </label>

                <output class="field-note" data-printing-note style="grid-column:1/-1"></output>
            </div>
        </div>
    </div>

    <div class="cost-step">
        <div class="cost-step-number">3</div>
        <div class="cost-step-body">
            <div class="section-title-row">
                <div>
                    <h3>Labor cost</h3>
                    <p class="muted">Enter the production cost and sewing cost for one piece.</p>
                </div>
            </div>
            <div class="form-grid form-grid-three cost-input-grid">
                <label>Production cost
                    <span class="money-input"><span>₱</span><input name="production_cost" type="number" step=".01" min="0" value="{{ old('production_cost',$product->production_cost ?? 0) }}" data-component="labor"></span>
                </label>
                <label>Sewing cost
                    <span class="money-input"><span>₱</span><input name="sewing_cost" type="number" step=".01" min="0" value="{{ old('sewing_cost',$product->sewing_cost ?? 0) }}" data-component="labor"></span>
                </label>
            </div>
        </div>
    </div>

    <div class="cost-step">
        <div class="cost-step-number">4</div>
        <div class="cost-step-body">
            <div class="section-title-row">
                <div>
                    <h3>Packaging</h3>
                    <p class="muted">Enter only the packaging used by this product. Leave an unused item at zero.</p>
                </div>
            </div>
            <div class="form-grid form-grid-three cost-input-grid">
                <label>Plastic
                    <span class="money-input"><span>₱</span><input name="plastic_cost" type="number" step=".01" min="0" value="{{ old('plastic_cost',$product->plastic_cost ?? 0) }}" data-component="packaging"></span>
                </label>
                <label>Box
                    <span class="money-input"><span>₱</span><input name="box_cost" type="number" step=".01" min="0" value="{{ old('box_cost',$product->box_cost ?? 0) }}" data-component="packaging"></span>
                </label>
                <label>Sticker
                    <span class="money-input"><span>₱</span><input name="sticker_cost" type="number" step=".01" min="0" value="{{ old('sticker_cost',$product->sticker_cost ?? 0) }}" data-component="packaging"></span>
                </label>
            </div>
        </div>
    </div>

    @php($margin = \App\Models\Setting::margin())
    <div class="costing-summary" data-costing-summary data-margin="{{ $margin }}">
        <div><span>Materials</span><b data-material-total>&#8369;0.00</b></div>
        <div><span>Printing</span><b data-printing-total>&#8369;0.00</b></div>
        <div><span>Labor <em>production + sewing</em></span><b data-labor-total>&#8369;0.00</b></div>
        <div><span>Packaging <em>plastic + box + sticker</em></span><b data-packaging-total>&#8369;0.00</b></div>
        <div class="costing-summary-grand"><span>Base product cost</span><b data-product-total>&#8369;0.00</b></div>
        <div data-area-row hidden><span>Additional material cost / cm&sup2; of print</span><b data-area-total>&#8369;0.0000</b></div>
        <div><span>Estimated selling price <em>{{ rtrim(rtrim(number_format($margin, 2), '0'), '.') }}% margin</em></span><b data-selling-total>&#8369;0.00</b></div>
        <p class="muted" data-costing-note>Select the materials used by this product.</p>
        <p class="muted" data-area-note hidden>Area-based materials are added when the quotation knows the artwork size. The base product cost above already includes printing, labor, and packaging.</p>
    </div>

    <label class="field-block">Description<textarea name="description">{{ old('description',$product->description) }}</textarea></label>
    <input type="hidden" name="is_active" value="1">

    <div class="form-actions">
        <button class="btn">Save product</button>
        <a class="btn btn-secondary" href="{{ route('admin.products.index') }}">Cancel</a>
    </div>
</form>

<style>
.cost-step{display:grid;grid-template-columns:42px minmax(0,1fr);gap:14px;padding:22px 0;border-top:1px solid var(--line)}.cost-step:first-of-type{margin-top:20px}.cost-step-number{width:34px;height:34px;border-radius:10px;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center;font-weight:800}.cost-step-body{min-width:0}.material-group{margin-top:18px}.material-group-title{font-weight:800;font-size:13px;margin-bottom:8px}.cost-input-grid{max-width:900px}.money-input{display:flex;align-items:center;border:1px solid var(--line);border-radius:10px;background:var(--surface);overflow:hidden}.money-input>span{padding:0 0 0 12px;color:var(--muted);font-weight:700}.money-input input{border:0!important;box-shadow:none!important}.costing-summary-grand{padding-top:12px!important;margin-top:5px;border-top:1px solid var(--line)}.costing-summary-grand b{font-size:20px}.material-group[hidden]{display:none}@media(max-width:760px){.cost-step{grid-template-columns:1fr}.cost-step-number{margin-bottom:-4px}}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const summary = document.querySelector('[data-costing-summary]');
    if (!summary) return;

    const peso = value => '₱' + value.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const number = value => parseFloat(value) || 0;
    const materialOut = summary.querySelector('[data-material-total]');
    const printingOut = summary.querySelector('[data-printing-total]');
    const laborOut = summary.querySelector('[data-labor-total]');
    const packagingOut = summary.querySelector('[data-packaging-total]');
    const productOut = summary.querySelector('[data-product-total]');
    const sellingOut = summary.querySelector('[data-selling-total]');
    const note = summary.querySelector('[data-costing-note]');
    const areaOut = summary.querySelector('[data-area-total]');
    const areaRow = summary.querySelector('[data-area-row]');
    const areaNote = summary.querySelector('[data-area-note]');
    const count = document.getElementById('product-material-count');

    function componentTotal(name) {
        return [...document.querySelectorAll(`[data-component="${name}"]`)]
            .reduce((sum, input) => sum + number(input.value), 0);
    }

    const methodSelect = document.querySelector('select[name="print_type"]');
    const printingNote = document.querySelector('[data-printing-note]');

    /* Printing is bought differently by each method, so only the fields the
       chosen one uses are shown. The others are disabled rather than merely
       hidden: a disabled field is not posted, so a stale stitch count cannot
       ride along on a silkscreen product. */
    function showMethodFields() {
        const method = methodSelect ? methodSelect.value : '';

        for (const field of document.querySelectorAll('[data-method-field]')) {
            const applies = field.dataset.methodField === method;
            field.hidden = !applies;
            field.querySelectorAll('input').forEach(input => { input.disabled = !applies; });
        }
    }

    /* The same arithmetic the server uses: the base covers the first design,
       and embroidery adds its stitches at the rate per stitch. */
    function printingCost() {
        const method = methodSelect ? methodSelect.value : '';
        const base = number(document.querySelector('[data-printing-base]').value);

        if (method === 'silkscreen') {
            const designs = Math.max(1, number(document.querySelector('[data-design-count]').value) || 1);
            const extra = number(document.querySelector('[data-extra-design]').value);
            const cost = base + (designs - 1) * extra;
            printingNote.textContent = designs > 1
                ? designs + ' designs: base ' + peso(base) + ' + ' + (designs - 1) + ' x ' + peso(extra) + ' = ' + peso(cost) + ' a piece'
                : 'One design, included in the base: ' + peso(cost) + ' a piece';
            return cost;
        }

        if (method === 'embroidery') {
            const stitches = number(document.querySelector('[data-stitch-count]').value);
            const rate = number(document.querySelector('[data-cost-per-stitch]').value);
            const cost = base + stitches * rate;
            printingNote.textContent = stitches > 0
                ? stitches.toLocaleString('en-PH') + ' stitches: base ' + peso(base) + ' + ' + peso(stitches * rate) + ' of stitching = ' + peso(cost) + ' a piece'
                : peso(cost) + ' a piece';
            return cost;
        }

        printingNote.textContent = peso(base) + ' a piece';
        return base;
    }

    function recalculate() {
        let materials = 0, perCm2 = 0, picked = 0, areaCount = 0;

        for (const row of document.querySelectorAll('.material-line')) {
            const ticked = row.querySelector('input[type="checkbox"]');
            const qty = row.querySelector('[data-qty]');
            qty.disabled = !ticked.checked;
            row.classList.toggle('is-picked', ticked.checked);
            if (!ticked.checked) continue;

            const amount = number(qty.value);
            const consumed = amount * (1 + (number(qty.dataset.waste) / 100));
            const cost = number(qty.dataset.cost);
            const divisor = number(qty.dataset.perCm2);

            if (divisor > 0) {
                perCm2 += (consumed / divisor) * cost;
                areaCount++;
            } else {
                materials += consumed * cost;
            }
            picked++;
        }

        const printing = printingCost();
        const labor = componentTotal('labor');
        const packaging = componentTotal('packaging');
        const total = materials + printing + labor + packaging;
        const margin = number(summary.dataset.margin);
        const selling = margin >= 100 || margin <= 0 ? total : total / (1 - (margin / 100));

        materialOut.textContent = peso(materials);
        printingOut.textContent = peso(printing);
        laborOut.textContent = peso(labor);
        packagingOut.textContent = peso(packaging);
        productOut.textContent = peso(total);
        sellingOut.textContent = peso(selling);
        areaOut.textContent = '₱' + perCm2.toLocaleString('en-PH', {minimumFractionDigits: 4, maximumFractionDigits: 4});
        areaRow.hidden = areaCount === 0;
        areaNote.hidden = areaCount === 0;

        note.textContent = picked
            ? picked + (picked === 1 ? ' material selected' : ' materials selected')
                + (areaCount ? ', ' + areaCount + ' priced by artwork size' : '')
            : 'Select the fabric, accessories, ribbings, and other materials this product needs.';
        if (count) count.textContent = picked + ' selected';
    }

    document.addEventListener('change', e => {
        if (e.target.matches('.material-line input, [data-component]')) recalculate();
        if (e.target.matches('select[name="print_type"]')) { showMethodFields(); recalculate(); }
    });
    document.addEventListener('input', e => {
        if (e.target.matches('[data-qty], [data-component], [data-design-count], [data-extra-design], [data-stitch-count], [data-cost-per-stitch]')) recalculate();
    });

    showMethodFields();

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
        for (const group of document.querySelectorAll('[data-material-group]')) {
            group.hidden = ![...group.querySelectorAll('.material-line')].some(row => !row.hidden);
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
