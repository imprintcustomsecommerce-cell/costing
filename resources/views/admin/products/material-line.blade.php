@php($chosen = $recipe->get($material->id))
<label class="choice-card material-line {{ $chosen ? 'is-picked' : '' }}"
    data-material-search="{{ strtolower($material->sku.' '.$material->name.' '.($material->category?->name ?? '')) }}">
    <input type="checkbox" name="materials[]" value="{{ $material->id }}"
        @checked(in_array($material->id, $selectedMaterials))>
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
