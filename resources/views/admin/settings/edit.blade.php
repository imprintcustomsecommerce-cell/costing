@extends('layouts.app')

@section('content')
<div class="top">
    <div>
        <div class="page-kicker">System administration</div>
        <h1>System settings</h1>
        <div class="muted">Company identity, quotation defaults, currency, margin protection, and tax</div>
    </div>
</div>

<form class="card" method="post" action="{{ route('admin.settings.update') }}">
    @csrf
    @method('put')
    @if($errors->any())<div class="alert-error">{{ $errors->first() }}</div>@endif
    @php $v=fn($key,$default='')=>old($key,$settings[$key]??$default); @endphp

    <div class="page-kicker">Company profile</div>
    <div class="form-grid">
        <label>Company name<input name="company_name" value="{{ $v('company_name','Imprint Customs') }}" required></label>
        <label>Company email<input name="company_email" type="email" value="{{ $v('company_email') }}"></label>
        <label>Phone<input name="company_phone" value="{{ $v('company_phone') }}"></label>
        <label>Website<input name="company_website" type="url" value="{{ $v('company_website') }}"></label>
        <label>Address<textarea name="company_address">{{ $v('company_address') }}</textarea></label>
    </div>

    <div class="form-section">
        <div class="page-kicker">Quotation defaults</div>
        <div class="form-grid form-grid-three">
            <label>Currency<input name="currency" value="{{ $v('currency','PHP') }}" required></label>
            <label>Quotation prefix<input name="quotation_prefix" value="{{ $v('quotation_prefix','QT') }}" required></label>
            <label>Validity (days)<input name="quotation_validity_days" type="number" value="{{ $v('quotation_validity_days',15) }}" required></label>
            <label>Default margin (%)<input name="default_margin" type="number" step=".01" value="{{ $v('default_margin') }}"></label>
            <label>Minimum gross margin (%)<input name="minimum_gross_margin" type="number" step=".01" value="{{ $v('minimum_gross_margin',30) }}" required></label>
            <label>Default labour per piece (₱)<input name="default_labour_cost" type="number" step=".01" min="0" value="{{ $v('default_labour_cost',0) }}"></label>
        </div>
    </div>

    <div class="form-section">
        <div class="page-kicker">Tax configuration</div>
        <div class="form-grid form-grid-three">
            <label>Tax enabled<select name="tax_enabled"><option value="0" @selected(!$v('tax_enabled',0))>No</option><option value="1" @selected($v('tax_enabled',0))>Yes</option></select></label>
            <label>Tax rate (%)<input name="tax_rate" type="number" step=".01" value="{{ $v('tax_rate',0) }}" required></label>
            <label>Tax inclusive<select name="tax_inclusive"><option value="0" @selected(!$v('tax_inclusive',0))>No</option><option value="1" @selected($v('tax_inclusive',0))>Yes</option></select></label>
        </div>
    </div>

    <div class="form-section">
        <div class="page-kicker">Production stages</div>
        <p class="muted">What each stage of the floor costs. A line on a quotation pays for the stages its print type actually runs — a full sublimation job runs laser cutting and the roller press, a DTF job runs neither. <b>Per piece</b> stages are charged on every piece; <b>per job</b> stages happen once however many pieces are ordered and are charged as setup, which is what makes a small run dearer per piece.</p>
        <div class="grid" style="grid-template-columns:repeat(3,1fr)">
            @foreach($stages as $stage)
                <label>{{ $stage->label }} <span class="cost-helper-optional">{{ $stage->basis === 'per_job' ? 'per job' : 'per piece' }}</span>
                    <input name="stages[{{ $stage->key }}]" type="number" step=".01" min="0" value="{{ rtrim(rtrim(number_format((float) $stage->rate, 2, '.', ''), '0'), '.') }}">
                </label>
            @endforeach
        </div>
    </div>

    <div class="form-section">
        <div class="page-kicker">Volume discounts</div>
        <p class="muted">From each quantity upwards, this much comes off the unit price. The biggest one an order reaches is the one it gets. A discount never cuts into cost — a line that would sell below what it costs is held at cost instead. Clear the quantity to remove a row.</p>
        <div id="break-rows">
            @foreach($breaks as $i => $tier)
                <div class="form-grid" style="grid-template-columns:1fr 1fr">
                    <label>From quantity<input name="breaks[{{ $i }}][min_quantity]" type="number" step="1" min="1" value="{{ (int) $tier->min_quantity }}"></label>
                    <label>Discount (%)<input name="breaks[{{ $i }}][discount_percentage]" type="number" step=".01" min="0" max="99.99" value="{{ rtrim(rtrim(number_format((float) $tier->discount_percentage, 2, '.', ''), '0'), '.') }}"></label>
                </div>
            @endforeach
        </div>
        <button type="button" class="btn btn-secondary btn-small" id="add-break">＋ Add discount tier</button>
        <template id="break-template">
            <div class="form-grid" style="grid-template-columns:1fr 1fr">
                <label>From quantity<input name="breaks[__i__][min_quantity]" type="number" step="1" min="1"></label>
                <label>Discount (%)<input name="breaks[__i__][discount_percentage]" type="number" step=".01" min="0" max="99.99"></label>
            </div>
        </template>
    </div>

    <div class="form-section">
        <div class="page-kicker">Rush fees</div>
        <p class="muted">A job wanted within this many days of the quotation pays this much on top of its subtotal. The tightest deadline it falls inside is the one it pays. Turnaround is charged on the quotation rather than on each unit price, so the customer can see what the deadline cost. Clear the days to remove a row.</p>
        <div id="rush-rows">
            @foreach($rushTiers as $i => $tier)
                <div class="form-grid" style="grid-template-columns:1fr 1fr">
                    <label>Needed within (days)<input name="rush[{{ $i }}][within_days]" type="number" step="1" min="0" max="365" value="{{ (int) $tier->within_days }}"></label>
                    <label>Rush fee (%)<input name="rush[{{ $i }}][surcharge_percentage]" type="number" step=".01" min="0" max="100" value="{{ rtrim(rtrim(number_format((float) $tier->surcharge_percentage, 2, '.', ''), '0'), '.') }}"></label>
                </div>
            @endforeach
        </div>
        <button type="button" class="btn btn-secondary btn-small" id="add-rush">＋ Add rush tier</button>
        <template id="rush-template">
            <div class="form-grid" style="grid-template-columns:1fr 1fr">
                <label>Needed within (days)<input name="rush[__i__][within_days]" type="number" step="1" min="0" max="365"></label>
                <label>Rush fee (%)<input name="rush[__i__][surcharge_percentage]" type="number" step=".01" min="0" max="100"></label>
            </div>
        </template>
    </div>

    <div class="form-actions"><button class="btn">Save settings</button></div>
</form>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.getElementById('break-rows');
    const template = document.getElementById('break-template');
    const add = document.getElementById('add-break');
    if (!rows || !template || !add) return;

    /* Continue the numbering from the rows already saved, so a new tier does
       not overwrite an existing one when the form posts. */
    let index = rows.querySelectorAll('.form-grid').length;

    add.addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__i__', index++);
        rows.append(document.createRange().createContextualFragment(html));
    });

    const rushRows = document.getElementById('rush-rows');
    const rushTemplate = document.getElementById('rush-template');
    const addRush = document.getElementById('add-rush');
    if (!rushRows || !rushTemplate || !addRush) return;

    let rushIndex = rushRows.querySelectorAll('.form-grid').length;

    addRush.addEventListener('click', () => {
        const html = rushTemplate.innerHTML.replaceAll('__i__', rushIndex++);
        rushRows.append(document.createRange().createContextualFragment(html));
    });
});
</script>
@endsection
