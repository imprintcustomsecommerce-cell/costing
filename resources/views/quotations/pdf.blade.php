<!doctype html>
<html><head><meta charset="utf-8">
<style>
    body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#1c2d35;margin:0}
    h1{font-size:20px;margin:0 0 2px}
    .muted{color:#6f7e80}
    table{width:100%;border-collapse:collapse;margin-top:18px}
    th{text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#6f7e80;
        border-bottom:1px solid #ccd1cb;padding:7px 8px}
    td{padding:8px;border-bottom:1px solid #e2e3dd}
    .num{text-align:right}
    .total{margin-top:16px;text-align:right;font-size:16px;font-weight:bold}
    .head{border-bottom:2px solid #1c2d35;padding-bottom:10px}
</style></head>
<body>
    <div class="head">
        <h1>{{ $settings['company_name'] ?? 'Imprint Customs' }}</h1>
        <div class="muted">Quotation {{ $quotation->number }} · {{ $quotation->created_at->toFormattedDateString() }}</div>
    </div>

    <p><strong>{{ $quotation->customer_name }}</strong><br>
    @if($quotation->customer_contact){{ $quotation->customer_contact }}@endif</p>

    <table>
        <thead><tr><th>Product</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead>
        <tbody>
        @foreach($quotation->items as $item)
            <tr>
                <td>{{ $item->product_name }}<br><span class="muted">{{ $item->product_sku }}@if($item->print_location) · {{ $item->print_location }}@endif @if($item->artworkAreaCm2()) · {{ rtrim(rtrim(number_format((float) $item->artwork_width_cm, 2), '0'), '.') }} x {{ rtrim(rtrim(number_format((float) $item->artwork_height_cm, 2), '0'), '.') }} cm @endif</span></td>
                <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ','), '0'), '.') }}</td>
                <td class="num">PHP {{ number_format((float) $item->unit_price, 2) }}</td>
                <td class="num">PHP {{ number_format((float) $item->line_total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if((float) $quotation->setup_cost > 0)
        <div class="total" style="font-size:13px;font-weight:normal">Setup (layout, mockup, sample, release): PHP {{ number_format((float) $quotation->setup_cost, 2) }}</div>
    @endif
    @if((float) $quotation->rush_percentage > 0)
        <div class="total" style="font-size:13px;font-weight:normal">Subtotal: PHP {{ number_format((float) $quotation->subtotal, 2) }}</div>
        <div class="total" style="font-size:13px;font-weight:normal">Rush fee ({{ rtrim(rtrim(number_format((float) $quotation->rush_percentage, 2), '0'), '.') }}%): PHP {{ number_format((float) $quotation->rush_amount, 2) }}</div>
    @endif
    <div class="total">Total: PHP {{ number_format((float) $quotation->total, 2) }}</div>

    @if($quotation->deadline)<p class="muted">Needed by {{ $quotation->deadline->toFormattedDateString() }}.</p>@endif
    @if($quotation->valid_until)<p class="muted">Valid until {{ $quotation->valid_until->toFormattedDateString() }}.</p>@endif
    @if($quotation->notes)<p>{{ $quotation->notes }}</p>@endif
</body></html>
