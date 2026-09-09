@php($selectedType = old('price_type', 'retail'))
<div class="price-choice" data-price-choice>
    <section>
        <label>Price type<select name="price_type" data-price-type><option value="retail" @selected($selectedType === 'retail')>Retail — direct price per unit</option><option value="bulk" @selected($selectedType === 'bulk')>Bulk — divide total by quantity</option></select></label>
        <div data-retail><h3>Retail price</h3><p class="muted">Enter the exact price of one <span data-unit-label>unit</span>.</p><label>Retail price / <span data-unit-label>unit</span><input name="retail_cost" type="number" step=".0001" min=".0001" value="{{ old('retail_cost') }}" placeholder="0.00"></label></div>
    </section>
    <section>
        <div data-bulk><h3>Bulk price</h3><p class="muted">The system calculates the price per <span data-unit-label>unit</span>.</p><div class="grid" style="grid-template-columns:1fr 1fr"><label>Total price paid<input name="bulk_total" data-bulk-total type="number" step=".0001" min=".0001" value="{{ old('bulk_total') }}" placeholder="0.00"></label><label><span data-unit-label>Units</span> received<input name="bulk_quantity" data-bulk-quantity type="number" step=".0001" min=".0001" value="{{ old('bulk_quantity') }}" placeholder="0"></label></div></div>
        <p class="bulk-result" data-bulk-result></p><input name="cost" data-cost type="hidden" value="{{ old('cost') }}">
    </section>
</div>
