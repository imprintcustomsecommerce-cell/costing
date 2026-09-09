@extends('layouts.app')
@section('content')
<div class="top">
    <div>
        <div class="page-kicker">Quotation</div>
        <h1>{{ $quotation->number }}</h1>
        <div class="muted">@if($quotation->customer)<a href="{{ route('customers.edit',$quotation->customer) }}">{{ $quotation->customer_name }}</a>@else{{ $quotation->customer_name }}@endif @if($quotation->customer_contact) · {{ $quotation->customer_contact }}@endif</div>
    </div>
    <div style="display:flex;gap:10px">
        <a class="btn btn-secondary" href="{{ route('quotations.index') }}">All quotations</a>
        <a class="btn btn-secondary" href="{{ route('quotations.edit',$quotation) }}">Edit</a>
        <form method="post" action="{{ route('quotations.duplicate',$quotation) }}" style="display:inline">@csrf<button class="btn btn-secondary" type="submit">Duplicate</button></form>
        <a class="btn" href="{{ route('quotations.pdf',$quotation) }}">Download PDF</a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="config-table">
            <thead><tr><th>Product</th><th>Print type</th><th>Print location</th><th>Artwork size</th><th class="num">Quantity</th><th class="num">Labour</th><th class="num">Unit price</th><th class="num">Line total</th></tr></thead>
            <tbody>
            @foreach($quotation->items as $item)
                <tr>
                    <td><span class="name">{{ $item->product_name }}</span><span class="sub">{{ $item->product_sku }}</span></td>
                    <td>{{ \App\Support\ProductionPipeline::label($item->print_type) }}</td>
                    <td>{{ $item->print_location ?: '—' }}@foreach($item->additionalLocations as $location)<span class="sub">{{ $location->location }}</span>@endforeach</td>
                    <td>@if($item->artworkAreaCm2()){{ rtrim(rtrim(number_format((float) $item->artwork_width_cm, 2), '0'), '.') }} × {{ rtrim(rtrim(number_format((float) $item->artwork_height_cm, 2), '0'), '.') }} cm<span class="sub">{{ number_format($item->artworkAreaCm2(), 0) }} cm²</span>@else<span class="muted">—</span>@endif@foreach($item->additionalLocations as $location)<span class="sub">{{ rtrim(rtrim(number_format((float) $location->width_cm, 2), '0'), '.') }} × {{ rtrim(rtrim(number_format((float) $location->height_cm, 2), '0'), '.') }} cm</span>@endforeach</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ','), '0'), '.') }}</td>
                    <td class="num">@if((float) $item->labour_cost > 0)₱{{ number_format((float) $item->labour_cost, 2) }}<span class="sub">per piece</span>@else<span class="muted">—</span>@endif</td>
                    <td class="num">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="num"><span class="name">₱{{ number_format((float) $item->line_total, 2) }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="costing-summary">
        @if((float) $quotation->setup_cost > 0)
            <div><span>Setup <span class="sub">layout, mockup, sample, release — charged once</span></span><b>₱{{ number_format((float) $quotation->setup_cost, 2) }}</b></div>
        @endif
        @if((float) $quotation->rush_percentage > 0)
            <div><span>Subtotal</span><b>₱{{ number_format((float) $quotation->subtotal, 2) }}</b></div>
            <div><span>Rush fee ({{ rtrim(rtrim(number_format((float) $quotation->rush_percentage, 2), '0'), '.') }}%)@if($quotation->deadline) <span class="sub">needed by {{ $quotation->deadline->toFormattedDateString() }}</span>@endif</span><b>₱{{ number_format((float) $quotation->rush_amount, 2) }}</b></div>
        @endif
        <div><span>Total</span><b>₱{{ number_format((float) $quotation->total, 2) }}</b></div>
        @if($quotation->valid_until)<div><span>Valid until</span><b style="font-size:16px">{{ $quotation->valid_until->toDateString() }}</b></div>@endif
        <p class="muted">Priced from each product's materials as they stood on {{ $quotation->created_at->toDateString() }}. Repricing a material since then does not change this quotation.</p>
    </div>

    @if($quotation->notes)
        <div class="form-section"><h3>Notes</h3><p class="muted">{{ $quotation->notes }}</p></div>
    @endif

    <p class="muted" style="margin-top:14px">Created by {{ $quotation->creator?->name ?? 'unknown' }} on {{ $quotation->created_at->toDayDateTimeString() }}.</p>
</div>

<section class="card" style="margin-top:20px">
    <div class="section-heading" style="margin-top:0">
        <div>
            <h2>Artwork</h2>
            <p class="muted">Files attached to this quotation. Stored privately and only downloadable while signed in.</p>
        </div>
    </div>

    @if($quotation->artworks->isNotEmpty())
    <div class="table-wrap" style="margin-bottom:16px">
        <table class="config-table">
            <thead><tr><th>File</th><th>Print location</th><th>Uploaded by</th><th class="num">Size</th><th>When</th><th></th></tr></thead>
            <tbody>
            @foreach($quotation->artworks as $artwork)
                <tr>
                    <td>
                        <span class="name">{{ $artwork->original_name }}</span>
                        @if($artwork->notes)<span class="sub">{{ $artwork->notes }}</span>@endif
                        @unless($artwork->exists())<span class="sub" style="color:var(--danger)">file missing from disk</span>@endunless
                    </td>
                    <td>{{ $artwork->print_location ?: '—' }}</td>
                    <td>{{ $artwork->uploader?->name ?? '—' }}</td>
                    <td class="num">{{ $artwork->humanSize() }}</td>
                    <td>{{ $artwork->created_at->toDateString() }}</td>
                    <td style="white-space:nowrap">
                        <a class="table-link" href="{{ route('artwork.download',$artwork) }}">Download</a>
                        <form method="post" action="{{ route('artwork.destroy',$artwork) }}" style="display:inline">
                            @csrf @method('delete')
                            <button class="table-link" style="background:none;border:0;color:var(--danger);cursor:pointer;padding:0 0 0 10px">Remove</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <form method="post" action="{{ route('artwork.store',$quotation) }}" enctype="multipart/form-data">
        @csrf
        <p class="muted" style="margin:0 0 12px">Add one file for each print location. You can upload several artwork files together.</p>
        <datalist id="artwork-location-options"><option value="Front chest"><option value="Full front"><option value="Back"><option value="Full back"><option value="Left sleeve"><option value="Right sleeve"><option value="Cap front"><option value="Cap side"></datalist>
        <div id="artwork-upload-rows">
            <div class="form-grid artwork-upload-row">
                <label>Artwork file
                    <input type="file" name="files[]" required accept=".png,.jpg,.jpeg,.gif,.webp,.svg,.pdf,.ai,.eps,.psd,.cdr">
                    <span class="cost-helper-note">PNG, JPG, GIF, WEBP, SVG, PDF, AI, EPS, PSD or CDR. Up to 50 MB.</span>
                </label>
                <label>Print location <span class="field-required">required</span>
                    <input name="print_locations[]" list="artwork-location-options" placeholder="e.g. Front chest" required>
                </label>
                <label>Note <span class="cost-helper-optional">optional</span>
                    <input name="notes[]" placeholder="e.g. revision 2">
                </label>
                <button type="button" class="btn btn-quiet btn-small artwork-row-remove" hidden>Remove</button>
            </div>
        </div>
        <div class="form-actions"><button type="button" class="btn btn-secondary" id="add-artwork-row">＋ Add another artwork</button><button class="btn">Upload artwork</button></div>
    </form>

    <template id="artwork-upload-row-template">
        <div class="form-grid artwork-upload-row">
            <label>Artwork file
                <input type="file" name="files[]" required accept=".png,.jpg,.jpeg,.gif,.webp,.svg,.pdf,.ai,.eps,.psd,.cdr">
            </label>
            <label>Print location <span class="field-required">required</span>
                <input name="print_locations[]" list="artwork-location-options" placeholder="e.g. Front chest" required>
            </label>
            <label>Note <span class="cost-helper-optional">optional</span>
                <input name="notes[]" placeholder="e.g. revision 2">
            </label>
            <button type="button" class="btn btn-quiet btn-small artwork-row-remove">Remove</button>
        </div>
    </template>
    <script>
        (() => {
            const rows = document.getElementById('artwork-upload-rows');
            const template = document.getElementById('artwork-upload-row-template');
            document.getElementById('add-artwork-row').addEventListener('click', () => rows.append(template.content.cloneNode(true)));
            rows.addEventListener('click', (event) => {
                if (event.target.closest('.artwork-row-remove')) event.target.closest('.artwork-upload-row').remove();
            });
        })();
    </script>
</section>

@endsection
