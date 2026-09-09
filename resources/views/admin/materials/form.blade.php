@extends('layouts.app')

@section('content')
<div class="top"><div><div class="page-kicker">Material master</div><h1 style="margin:0">{{ $material->exists ? $material->name : 'New material' }}</h1><div class="muted">One material has one active price: retail or bulk.</div></div><a class="btn btn-secondary" href="{{ route('admin.materials.index') }}">Back to materials</a></div>

<form class="card" method="post" action="{{ $material->exists ? route('admin.materials.update', $material) : route('admin.materials.store') }}">
    @csrf @if($material->exists) @method('put') @endif
    @if($errors->any())<div class="alert-error">{{ $errors->first() }}</div>@endif
    <div class="grid" style="grid-template-columns:repeat(3,1fr)">
        <label>SKU<input name="sku" value="{{ old('sku',$material->sku) }}" required></label>
        <label>Material name<input name="name" value="{{ old('name',$material->name) }}" required></label>
        <label>Supplier<input name="supplier" value="{{ old('supplier',$material->supplier) }}"></label>
        <label>Unit<select name="unit" data-unit>@foreach(\App\Models\Material::UNITS as $unit)<option value="{{ $unit }}" @selected(old('unit',$material->unit)===$unit)>{{ str_replace('_',' ',$unit) }}</option>@endforeach</select></label>
        <label>Waste %<input name="waste_percentage" type="number" step=".0001" min="0" max="100" value="{{ old('waste_percentage',$material->waste_percentage ?? 0) }}" required></label>
        <label>Category<select name="material_category_id"><option value="">—</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('material_category_id',$material->material_category_id)==$category->id)>{{ $category->name }}</option>@endforeach</select></label>
        <label data-roll-size>Roll/sheet width (cm)<input name="width_cm" type="number" step=".0001" min=".0001" value="{{ old('width_cm',$material->width_cm) }}"></label>
        <label data-roll-size>Roll/sheet length (cm)<input name="length_cm" type="number" step=".0001" min=".0001" value="{{ old('length_cm',$material->length_cm) }}"></label>
        <label>Consumed per m² of print <span class="muted small">optional</span><input name="coverage_per_sqm" type="number" step=".0001" min="0" value="{{ old('coverage_per_sqm',$material->coverage_per_sqm) }}"></label>
    </div>
    <input type="hidden" name="is_active" value="1">

    @if($material->exists)
        <div class="current-price"><span>Current quotation cost / <span data-unit-label>unit</span></span><b>{{ $material->effectiveCostToday() === null ? 'Not set' : '₱'.number_format($material->effectiveCostToday(),4) }}</b><span class="muted">{{ $material->retail_cost !== null ? 'Retail price' : 'Bulk price' }}</span></div>
    @else
        @include('admin.materials.price-inputs', ['prefix' => ''])
        <div class="grid" style="grid-template-columns:1fr 1fr;margin-top:16px"><label>Effective date<input name="effective_from" type="date" value="{{ old('effective_from',date('Y-m-d')) }}" required></label><label>Reason<input name="reason" value="{{ old('reason','Initial configuration') }}" required></label></div>
    @endif
    <button class="btn" style="margin-top:18px">Save material details</button>
</form>

@if($material->exists)
<form class="card" style="margin-top:20px" method="post" action="{{ route('admin.materials.cost',$material) }}">
    @csrf
    <div class="page-kicker">Price update</div><h2>Choose the new material price</h2>
    <p class="muted">Choose one: enter a direct retail unit price, or enter a bulk purchase total and quantity. New quotations use this price; old quotations stay unchanged.</p>
    @include('admin.materials.price-inputs', ['prefix' => 'update_'])
    <div class="grid" style="grid-template-columns:1fr 1fr;margin-top:16px"><label>Effective date<input name="effective_from" type="date" value="{{ date('Y-m-d') }}" required></label><label>Reason<input name="reason" placeholder="Supplier price change" required></label></div>
    <button class="btn" style="margin-top:18px">Save new price</button>
    <div class="table-wrap" style="margin-top:22px"><table><thead><tr><th>Date</th><th>Cost / unit</th><th>Previous</th><th>Change</th><th>Reason</th></tr></thead><tbody>@foreach($material->costHistories as $history)<tr><td>{{ $history->effective_from->format('Y-m-d') }}</td><td>₱{{ number_format((float)$history->cost,4) }}</td><td>{{ $history->previous_cost ? '₱'.number_format((float)$history->previous_cost,4) : '—' }}</td><td>{{ $history->change_percentage ? number_format((float)$history->change_percentage,2).'%' : '—' }}</td><td>{{ $history->reason }}</td></tr>@endforeach</tbody></table></div>
</form>
@endif

<style>
.price-choice { display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:22px }.price-choice section{border:1px solid var(--line);border-radius:14px;padding:18px;background:var(--surface-soft)}.price-choice section.selected{border-color:var(--accent);background:var(--accent-soft)}.price-choice h3{margin:4px 0 7px}.current-price{display:flex;align-items:center;gap:14px;margin-top:20px;padding:15px;border:1px solid var(--line);border-radius:12px}.current-price b{font-size:18px;color:var(--accent-strong)}.small{font-size:11px;font-weight:400}.bulk-result{margin:12px 0 0;color:var(--accent-strong);font-variant-numeric:tabular-nums}@media(max-width:760px){.price-choice{grid-template-columns:1fr}.current-price{align-items:flex-start;flex-direction:column;gap:4px}}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{const units={piece:'piece',meter:'meter',yard:'yard',linear_meter:'linear meter',square_meter:'square meter',square_cm:'square cm',roll:'roll',sheet:'sheet',gram:'gram',kilogram:'kilogram',pack:'pack',set:'set'},unit=document.querySelector('[data-unit]'),roll=['roll','sheet','square_meter','square_cm'];function labels(){let text=units[unit?.value]||'unit';document.querySelectorAll('[data-unit-label]').forEach(x=>x.textContent=text);document.querySelectorAll('[data-roll-size]').forEach(x=>x.hidden=!roll.includes(unit?.value))}document.querySelectorAll('[data-price-choice]').forEach(box=>{let type=box.querySelector('[data-price-type]'),retail=box.querySelector('[data-retail]'),bulk=box.querySelector('[data-bulk]'),total=box.querySelector('[data-bulk-total]'),qty=box.querySelector('[data-bulk-quantity]'),cost=box.querySelector('[data-cost]'),out=box.querySelector('[data-bulk-result]');function sync(){let isRetail=type.value==='retail';retail.hidden=!isRetail;bulk.hidden=isRetail;retail.parentElement.classList.toggle('selected',isRetail);bulk.parentElement.classList.toggle('selected',!isRetail);if(isRetail){cost.value=retail.querySelector('input').value;out.textContent='The entered retail unit price will be used in quotations.';return}let paid=Number(total.value),received=Number(qty.value);if(!(paid>0&&received>0)){cost.value='';out.textContent='Enter the bulk total and quantity to calculate the unit price.';return}let value=paid/received;cost.value=value.toFixed(4);out.innerHTML=`₱${paid.toLocaleString('en-PH')} ÷ ${received.toLocaleString('en-PH')} = <b>₱${value.toFixed(4)}</b> per ${units[unit?.value]||'unit'}.`}type.addEventListener('change',sync);retail.querySelector('input').addEventListener('input',sync);total.addEventListener('input',sync);qty.addEventListener('input',sync);sync()});unit?.addEventListener('change',labels);labels()});
</script>
@endsection
