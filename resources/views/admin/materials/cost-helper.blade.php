{{--
    Works the unit cost out from a supplier invoice.

    A cost is stored per unit, but suppliers price by the roll, box or pack. Doing
    that division by hand is where the wrong figure gets typed — a roll at ₱1,530
    entered against a square-metre unit prices every job thirty times over. These
    inputs carry no name attribute, so nothing here is submitted: they only fill
    the cost field, which stays the single value the server validates and stores.

    @param string|null $unit    fixed unit, or null to follow a unit select on the page
    @param mixed       $width   fixed roll width in cm, or null to follow the width input
    @param mixed       $length  fixed roll length in cm, or null to follow the length input
--}}
<div class="cost-helper" data-cost-helper
    @isset($unit) data-unit="{{ $unit }}" @endisset
    @isset($width) data-width="{{ $width }}" @endisset
    @isset($length) data-length="{{ $length }}" @endisset>
    <p class="cost-helper-intro">Bought by the roll, box or pack? Enter the invoice figures and the unit cost is worked out for you.</p>
    <div class="cost-helper-fields">
        <label>Total price paid
            <input type="number" step=".0001" min="0" data-paid placeholder="1530">
        </label>
        <label><span data-unit-label>Units</span> in what you bought
            <input type="number" step=".0001" min="0" data-received placeholder="1000">
            <span class="cost-helper-note" data-amount-note hidden></span>
        </label>
        <label data-per-print hidden><span data-unit-label-2>Units</span> per print <span class="cost-helper-optional">optional</span>
            <input type="number" step=".0001" min="0" data-per-print-input placeholder="0.5">
        </label>
    </div>
    <p class="cost-helper-working" data-working hidden></p>
    <p class="cost-helper-yield" data-yield hidden></p>
</div>
