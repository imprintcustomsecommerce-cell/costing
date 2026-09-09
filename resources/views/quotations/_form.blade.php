@php($editing = isset($quotation) && $quotation)
@php($existing = $editing ? $quotation->items->map(fn ($item) => [
    'product_id' => (string) $item->product_id,
    'quantity' => (float) $item->quantity,
    'width' => $item->artwork_width_cm ? (float) $item->artwork_width_cm : null,
    'height' => $item->artwork_height_cm ? (float) $item->artwork_height_cm : null,
    'location' => $item->print_location,
    'additional_locations' => $item->additionalLocations->map(fn ($location) => ['location' => $location->location, 'width' => (float) $location->width_cm, 'height' => (float) $location->height_cm])->values(),
])->values() : collect())

<form class="card" method="post" action="{{ $editing ? route('quotations.update', $quotation) : route('quotations.store') }}">
    @csrf
    @if($editing)@method('PUT')@endif
    @if($errors->any())<div class="alert-error">{{ $errors->first() }}</div>@endif

    <div class="form-grid form-grid-three">
        <label>Customer
            <select name="customer_id" id="customer">
                <option value="">— New customer (fill in below) —</option>
                @foreach($customers as $option)
                    <option value="{{ $option->id }}" @selected(old('customer_id', $editing ? $quotation->customer_id : null)==$option->id)>{{ $option->code }} — {{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
        <label>Needed by <span class="cost-helper-optional">optional</span>
            <input type="date" name="deadline" data-deadline value="{{ old('deadline', $editing && $quotation->deadline ? $quotation->deadline->format('Y-m-d') : '') }}">
            <output class="field-note" data-rush-note>A tight deadline adds a rush fee.</output>
        </label>
    </div>

    <div id="new-customer" class="form-grid form-grid-three" style="{{ old('customer_id', $editing ? $quotation->customer_id : null) ? 'display:none' : '' }}">
        <label>Contact person<input name="customer_name" value="{{ old('customer_name', $editing && ! $quotation->customer_id ? $quotation->customer_name : '') }}" placeholder="e.g. Juan Dela Cruz"></label>
        <label>Company <span class="cost-helper-optional">optional</span><input name="customer_company" value="{{ old('customer_company') }}" placeholder="e.g. Falcon Riders MC"></label>
        <label>Phone <span class="cost-helper-optional">optional</span><input name="customer_contact" value="{{ old('customer_contact', $editing ? $quotation->customer_contact : '') }}" placeholder="e.g. 0917-555-1234"></label>
    </div>

    <div class="form-section">
        <div class="section-title-row">
            <div><h3>Products</h3><p class="muted">Add a line per product. Only products with priced materials are listed.</p></div>
        </div>

        <div id="quotation-lines"></div>
        <button type="button" class="btn btn-secondary btn-small" id="add-line">＋ Add product</button>
        <p class="muted" id="no-lines" hidden>No products added yet.</p>

        <template id="line-template">
            <div class="quote-item-editor">
            <div class="quote-line">
                <label class="quote-line-product">Product
                    <select name="items[__i__][product_id]" data-product required>
                        <option value="">Select a product</option>
                        @foreach($products as $product)
                            <option value="{{ $product['id'] }}"
                                data-materials="{{ $product['materials'] }}"
                                data-materials-per-cm2="{{ $product['materials_per_cm2'] }}"
                                data-stage-labour="{{ $product['labour'] }}"
                                data-stage-setup="{{ $product['setup'] }}"
                                data-needs-artwork="{{ $product['needs_artwork'] ? '1' : '' }}">{{ $product['sku'] }} — {{ $product['name'] }} ({{ $product['print_type_label'] }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="quote-line-quantity">Quantity<input type="number" step="1" min="1" name="items[__i__][quantity]" value="1" data-qty required></label>
                <label class="quote-line-artwork" data-artwork hidden>Artwork size (cm)
                    <span class="artwork-size">
                        <input type="number" step=".01" min="0.01" name="items[__i__][artwork_width_cm]" placeholder="W" data-width>
                        <span>×</span>
                        <input type="number" step=".01" min="0.01" name="items[__i__][artwork_height_cm]" placeholder="H" data-height>
                    </span>
                </label>
                <label class="quote-line-location">Print location <span class="field-required">required</span>
                    <input name="items[__i__][print_location]" list="quote-print-locations" placeholder="e.g. Front chest" data-location required>
                </label>
                <div class="quote-line-figure quote-line-unit"><span>Unit price</span><output data-unit>₱0.00</output><small data-discount hidden></small></div>
                <div class="quote-line-figure is-total quote-line-total"><span>Line total</span><output data-line>₱0.00</output></div>
                <button type="button" class="btn btn-quiet btn-small quote-line-remove" data-remove-line aria-label="Remove this line" title="Remove this line">Remove</button>
            </div>
            <div class="additional-location-list" data-extra-list></div>
            <button type="button" class="btn btn-secondary btn-small" data-add-location>＋ Add additional location</button>
            </div>
        </template>
        <template id="additional-location-template">
            <div class="additional-location-row">
                <strong>Additional location</strong>
                <label>Location <span class="field-required">required</span><input list="quote-print-locations" placeholder="e.g. Back" data-extra-location required></label>
                <label>Artwork size (cm)<span class="artwork-size"><input type="number" step=".01" min="0.01" placeholder="W" data-extra-width required><span>×</span><input type="number" step=".01" min="0.01" placeholder="H" data-extra-height required></span></label>
                <button type="button" class="btn btn-quiet btn-small" data-remove-location>Remove</button>
            </div>
        </template>
    </div>

    <div class="quote-totals">
        <dl>
            <div data-setup-row hidden>
                <dt>Setup <small>layout, mockup, sample, release — once per job</small></dt>
                <dd data-setup-total>₱0.00</dd>
            </div>
            <div data-subtotal-row hidden>
                <dt>Subtotal</dt>
                <dd data-subtotal>₱0.00</dd>
            </div>
            <div data-rush-row hidden>
                <dt data-rush-label>Rush fee</dt>
                <dd data-rush>₱0.00</dd>
            </div>
            <div class="is-total">
                <dt>Quotation total</dt>
                <dd data-total>₱0.00</dd>
            </div>
        </dl>
        <p class="muted">Each line costs its materials plus the production stages its print type runs — printing, cutting, pressing, pairing, sewing, QC and inventory. Layout, mockup, sample and release are charged once, as setup.</p>
        <p class="muted">@if($validityDays > 0)This price stands for {{ $validityDays }} {{ \Illuminate\Support\Str::plural('day', $validityDays) }} from today.@endif Print type is set on the product; stage rates in Settings. Anything printed on film or vinyl is costed by the artwork size.@if($breaks->isNotEmpty()) Volume discounts start at {{ (int) $breaks->last()['min'] }} pieces.@endif</p>
    </div>

    <label class="field-block">Notes <span class="cost-helper-optional">optional</span><textarea name="notes">{{ old('notes', $editing ? $quotation->notes : '') }}</textarea></label>

    <div class="form-actions">
        <button class="btn">{{ $editing ? 'Save changes' : 'Save quotation' }}</button>
        <a class="btn btn-secondary" href="{{ $editing ? route('quotations.show', $quotation) : route('quotations.index') }}">Cancel</a>
    </div>
</form>

<datalist id="quote-print-locations"><option value="Front chest"><option value="Full front"><option value="Back"><option value="Full back"><option value="Left sleeve"><option value="Right sleeve"><option value="Cap front"><option value="Cap side"></datalist>

<script id="quote-breaks" type="application/json">@json($breaks)</script>
<script id="quote-lines" type="application/json">@json($existing)</script>
<script id="quote-rush" type="application/json">@json($rushTiers)</script>

<script>
/* Line prices shown here are a preview of what the server will compute from the
   product's materials, its labour and the volume breaks. The form never posts a
   price -- only which product and how many -- so what is stored cannot be
   edited from the browser. */
document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('quotation-lines');
    const template = document.getElementById('line-template');
    const addButton = document.getElementById('add-line');
    const empty = document.getElementById('no-lines');
    const totalOut = document.querySelector('[data-total]');
    if (!list || !template) return;

    const read = id => { try { return JSON.parse(document.getElementById(id).textContent); } catch (e) { return []; } };
    const breaks = read('quote-breaks');
    const saved = read('quote-lines');
    const margin = {{ (float) $margin }};
    const rushTiers = read('quote-rush');

    /* Tightest deadline first, so the first tier the job falls inside is the
       one it pays -- the same rule the server applies. A deadline already past
       counts as nought days rather than falling out of every tier. */
    function rushFor(deadline) {
        if (!deadline) return 0;
        const wanted = new Date(deadline + 'T00:00:00');
        if (isNaN(wanted)) return 0;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const days = Math.max(0, Math.round((wanted - today) / 86400000));
        const tier = rushTiers.find(t => days <= t.days);
        return tier ? tier.surcharge : 0;
    }

    let index = 0;
    const peso = value => '₱' + value.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    /* Biggest quantity first, so the first break the order reaches is the most
       generous one it has earned -- the same rule the server applies. */
    const discountFor = qty => (breaks.find(tier => qty >= tier.min) || {discount: 0}).discount;

    function recalculate() {
        const rows = [...list.querySelectorAll('.quote-item-editor')];
        empty.hidden = rows.length > 0;

        /* The server refuses duplicate products, so a product already on one
           line is disabled on the others. */
        const taken = new Set(rows.map(r => r.querySelector('[data-product]').value).filter(Boolean));
        let total = 0;

        for (const row of rows) {
            const select = row.querySelector('[data-product]');
            for (const option of select.options) {
                option.disabled = option.value !== '' && option.value !== select.value && taken.has(option.value);
            }

            const chosen = select.selectedOptions[0];
            const artwork = row.querySelector('[data-artwork]');
            const width = row.querySelector('[data-width]');
            const height = row.querySelector('[data-height]');

            /* Only products printed on material sold by area need a size, and
               a hidden field would otherwise post a stale one. */
            const needsArtwork = !!(chosen && chosen.dataset.needsArtwork);
            artwork.hidden = !needsArtwork;
            width.disabled = height.disabled = !needsArtwork;
            width.required = height.required = needsArtwork;

            let area = needsArtwork
                ? (parseFloat(width.value) || 0) * (parseFloat(height.value) || 0)
                : 0;
            row.querySelectorAll('[data-extra-width]').forEach((extraWidth, extraIndex) => {
                const extraHeight = row.querySelectorAll('[data-extra-height]')[extraIndex];
                area += (parseFloat(extraWidth.value) || 0) * (parseFloat(extraHeight.value) || 0);
            });

            /* Materials, then the minutes actually typed on this line, then
               the margin -- the same order the server costs it in. */
            let cost = chosen ? parseFloat(chosen.dataset.materials || 0) : 0;
            if (needsArtwork) cost += area * parseFloat(chosen.dataset.materialsPerCm2 || 0);

            /* The per-piece stages the product's route runs through the floor.
               How a product is made is the product's own business, so nothing
               here is chosen -- it follows from which product was picked. */
            cost += chosen ? (parseFloat(chosen.dataset.stageLabour) || 0) : 0;

            const listPrice = margin > 0 && margin < 100 ? cost / (1 - margin / 100) : cost;

            const qty = parseFloat(row.querySelector('[data-qty]').value) || 0;

            /* A discount may never cut into the cost, so the preview holds at
               cost exactly as the server does. */
            let discount = discountFor(qty);
            let unit = listPrice * (1 - discount / 100);
            if (unit < cost) {
                unit = cost;
                discount = listPrice > 0 ? (1 - unit / listPrice) * 100 : 0;
            }
            unit = Math.round(unit * 100) / 100;

            const line = Math.round(unit * qty * 100) / 100;
            total += line;

            const note = row.querySelector('[data-discount]');
            note.hidden = discount <= 0;
            note.textContent = discount > 0 ? 'less ' + discount.toFixed(1) + '% volume' : '';

            row.querySelector('[data-unit]').textContent = peso(unit);
            row.querySelector('[data-line]').textContent = peso(line);
        }

        /* Layout, the mockup, the sample and releasing the order happen once
           however many pieces are ordered, so the routes on the quotation are
           unioned and charged once -- the same rule the server applies. */
        /* Every route runs the same once-per-job stages, so a quotation pays a
           single setup however many products it mixes -- two lines do not buy
           two layouts. Taking the largest is the union of identical sets. */
        const setupCost = rows.reduce((most, r) => {
            const option = r.querySelector('[data-product]').selectedOptions[0];
            return Math.max(most, option ? (parseFloat(option.dataset.stageSetup) || 0) : 0);
        }, 0);
        const setup = Math.round((margin > 0 && margin < 100 ? setupCost / (1 - margin / 100) : setupCost) * 100) / 100;
        total += setup;

        const setupRow = document.querySelector('[data-setup-row]');
        setupRow.hidden = setup <= 0;
        document.querySelector('[data-setup-total]').textContent = peso(setup);

        /* Turnaround is charged on the subtotal, not on each unit price: it is
           what the deadline costs, not what a piece is worth. */
        const deadline = document.querySelector('[data-deadline]');
        const surcharge = rushFor(deadline ? deadline.value : '');
        const rush = Math.round(total * (surcharge / 100) * 100) / 100;

        document.querySelector('[data-subtotal-row]').hidden = surcharge <= 0 && setup <= 0;
        document.querySelector('[data-rush-row]').hidden = surcharge <= 0;
        document.querySelector('[data-subtotal]').textContent = peso(total);
        document.querySelector('[data-rush-label]').textContent =
            'Rush fee (' + surcharge.toFixed(surcharge % 1 ? 2 : 0) + '%)';
        document.querySelector('[data-rush]').textContent = peso(rush);

        const note = document.querySelector('[data-rush-note]');
        const dated = deadline && deadline.value;
        note.textContent = surcharge > 0
            ? 'Rush fee of ' + surcharge.toFixed(surcharge % 1 ? 2 : 0) + '% applies.'
            : (dated ? 'No rush fee for this deadline.' : 'A tight deadline adds a rush fee.');
        note.classList.toggle('is-warning', surcharge > 0);

        totalOut.textContent = peso(total + rush);
    }

    function addLine(values) {
        const itemIndex = index++;
        const html = template.innerHTML.replaceAll('__i__', itemIndex);
        const row = document.createRange().createContextualFragment(html).firstElementChild;
        row.dataset.itemIndex = itemIndex;
        list.append(row);

        if (values) {
            row.querySelector('[data-product]').value = values.product_id;
            row.querySelector('[data-qty]').value = values.quantity;
            if (values.width) row.querySelector('[data-width]').value = values.width;
            if (values.height) row.querySelector('[data-height]').value = values.height;
            if (values.location) row.querySelector('[data-location]').value = values.location;
            (values.additional_locations || []).forEach(location => addLocation(row, location));
        }

        row.querySelector('[data-remove-line]').addEventListener('click', () => { row.remove(); recalculate(); });
        row.querySelector('[data-add-location]').addEventListener('click', () => addLocation(row));
        recalculate();
    }

    function addLocation(row, values = null) {
        const locationIndex = row.querySelectorAll('.additional-location-row').length;
        const extra = document.createRange().createContextualFragment(document.getElementById('additional-location-template').innerHTML).firstElementChild;
        const prefix = `items[${row.dataset.itemIndex}][additional_locations][${locationIndex}]`;
        extra.querySelector('[data-extra-location]').name = `${prefix}[location]`;
        extra.querySelector('[data-extra-width]').name = `${prefix}[width]`;
        extra.querySelector('[data-extra-height]').name = `${prefix}[height]`;
        if (values) {
            extra.querySelector('[data-extra-location]').value = values.location || '';
            extra.querySelector('[data-extra-width]').value = values.width || '';
            extra.querySelector('[data-extra-height]').value = values.height || '';
        }
        extra.querySelector('[data-remove-location]').addEventListener('click', () => { extra.remove(); recalculate(); });
        row.querySelector('[data-extra-list]').append(extra);
        recalculate();
    }

    addButton.addEventListener('click', () => addLine(null));
    document.addEventListener('change', e => { if (e.target.matches('[data-product]')) recalculate(); });
    document.addEventListener('input', e => { if (e.target.matches('[data-qty], [data-width], [data-height], [data-extra-width], [data-extra-height]')) recalculate(); });
    document.addEventListener('change', e => { if (e.target.matches('[data-deadline]')) recalculate(); });
    document.addEventListener('input', e => { if (e.target.matches('[data-deadline]')) recalculate(); });

    /* An existing customer already has their details, so choosing one hides
       and disables the new-customer block. Disabled fields are not posted,
       which is what keeps required_without from firing on a customer who was
       picked rather than typed. */
    const customer = document.getElementById('customer');
    const newCustomer = document.getElementById('new-customer');

    function toggleCustomer() {
        const isNew = !customer.value;
        newCustomer.style.display = isNew ? '' : 'none';
        newCustomer.querySelectorAll('input').forEach(field => field.disabled = !isNew);
        newCustomer.querySelector('[name="customer_name"]').required = isNew;
    }

    customer.addEventListener('change', toggleCustomer);
    toggleCustomer();

    /* An edited quotation opens on the lines it already has; a new one opens
       on a single blank line to fill in. */
    if (saved.length) { saved.forEach(addLine); } else { addLine(null); }
});
</script>
