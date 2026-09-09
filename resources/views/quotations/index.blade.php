@extends('layouts.app')
@section('content')
<div class="top">
    <div>
        <div class="page-kicker">Sales</div>
        <h1>Quotations</h1>
        <div class="muted">Products and quantities, priced from what their materials cost.</div>
    </div>
    <a class="btn" href="{{ route('quotations.create') }}">＋ New quotation</a>
</div>

<div class="card">
    <form style="max-width:430px">
        <label>Search quotations<input name="q" value="{{ request('q') }}" placeholder="Number or customer"></label>
    </form>

    <div class="table-wrap" style="margin-top:18px">
        <table class="config-table">
            <thead><tr>
                <th>Number</th><th>Customer</th>
                <th class="num">Lines</th><th class="num">Total</th><th>Created</th><th></th>
            </tr></thead>
            <tbody>
            @forelse($quotations as $quotation)
                <tr>
                    <td><span class="name">{{ $quotation->number }}</span></td>
                    <td>{{ $quotation->customer_name }}@if($quotation->customer_contact)<span class="sub">{{ $quotation->customer_contact }}</span>@endif</td>
                    <td class="num">{{ $quotation->items_count }}</td>
                    <td class="num"><span class="name">₱{{ number_format((float) $quotation->total, 2) }}</span></td>
                    <td>{{ $quotation->created_at->toDateString() }}</td>
                    <td><a class="table-link" href="{{ route('quotations.show',$quotation) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-state">No quotations yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $quotations->links() }}
</div>
@endsection
