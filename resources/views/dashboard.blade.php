@extends('layouts.app')

@section('content')
@if(auth()->user()->hasAnyRole(['SUPER ADMIN', 'ADMIN']))
<div class="top">
    <div>
        <div class="page-kicker">Overview</div>
        <h1>Dashboard</h1>
        <div class="muted">What a product costs, from the materials it is made of.</div>
    </div>
    <a class="btn" href="{{ route('admin.products.create') }}">＋ New product</a>
</div>

<div class="grid">
    <div class="card metric">
        <div class="metric-label"><span>Products</span><span class="metric-icon">P</span></div>
        <b>{{ number_format($productCount) }}</b>
    </div>
    <div class="card metric tone-blue">
        <div class="metric-label"><span>Materials</span><span class="metric-icon">M</span></div>
        <b>{{ number_format($materialCount) }}</b>
    </div>
    <div class="card metric tone-violet">
        <div class="metric-label"><span>Average product cost</span><span class="metric-icon">₱</span></div>
        <b>₱{{ number_format($averageCost, 2) }}</b>
    </div>
    <div class="card metric {{ $unpriced->isNotEmpty() || $costless->isNotEmpty() ? 'tone-gold' : '' }}">
        <div class="metric-label"><span>Needs attention</span><span class="metric-icon">!</span></div>
        <b>{{ number_format($unpriced->count() + $costless->count()) }}</b>
    </div>
</div>

@if($unpriced->isNotEmpty() || $costless->isNotEmpty())
<div class="card" style="margin-top:22px">
    <div class="section-heading" style="margin-top:0">
        <div>
            <h2>Needs attention</h2>
            <p class="muted">These cost nothing today, which is almost certainly wrong.</p>
        </div>
    </div>

    @if($unpriced->isNotEmpty())
        <h3 style="margin-bottom:6px">Products with no materials</h3>
        <div class="choice-grid">
            @foreach($unpriced as $product)
                <a class="choice-card" href="{{ route('admin.products.edit', $product) }}">
                    <span>{{ $product->name }}<em class="muted" style="display:block;font-style:normal;font-size:11px">{{ $product->sku }}</em></span>
                </a>
            @endforeach
        </div>
    @endif

    @if($costless->isNotEmpty())
        <h3 style="margin:18px 0 6px">Materials with no cost</h3>
        <div class="choice-grid">
            @foreach($costless as $material)
                <a class="choice-card" href="{{ route('admin.materials.edit', $material) }}">
                    <span>{{ $material->name }}<em class="muted" style="display:block;font-style:normal;font-size:11px">{{ $material->sku }}</em></span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endif

<div class="card" style="margin-top:22px">
    <div class="section-heading" style="margin-top:0">
        <div>
            <h2>Products</h2>
            <p class="muted">Most recently added, with what their materials cost.</p>
        </div>
        <a class="btn btn-secondary btn-small" href="{{ route('admin.products.index') }}">All products</a>
    </div>
    <div class="table-wrap">
        <table class="config-table">
            <thead><tr>
                <th>Product</th><th>Category</th><th class="num">Materials</th>
                <th class="num">Bulk cost</th><th class="num">Retail cost</th>
            </tr></thead>
            <tbody>
            @forelse($recentProducts as $product)
                @php($costing = $product->costing())
                <tr>
                    <td><a class="name" href="{{ route('admin.products.edit', $product) }}">{{ $product->name }}</a><span class="sub">{{ $product->sku }}</span></td>
                    <td>{{ $product->category?->name ?: '—' }}</td>
                    <td class="num">{{ $product->materials->count() }}</td>
                    <td class="num"><span class="name">₱{{ number_format($costing['bulk'], 2) }}</span></td>
                    <td class="num">₱{{ number_format($costing['retail'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-state">No products yet. Add one to see its cost.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($costAlerts->isNotEmpty())
<div class="card" style="margin-top:22px">
    <div class="section-heading" style="margin-top:0">
        <div>
            <h2>Material cost changes</h2>
            <p class="muted">Movements of 5% or more, newest first.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="config-table">
            <thead><tr><th>Material</th><th class="num">Was</th><th class="num">Now</th><th class="num">Change</th><th>Effective</th></tr></thead>
            <tbody>
            @foreach($costAlerts as $change)
                <tr>
                    <td><span class="name">{{ $change->material?->name }}</span><span class="sub">{{ $change->material?->sku }}</span></td>
                    <td class="num">₱{{ number_format((float) $change->previous_cost, 2) }}</td>
                    <td class="num">₱{{ number_format((float) $change->cost, 2) }}</td>
                    <td class="num">{{ $change->change_percentage > 0 ? '+' : '' }}{{ number_format((float) $change->change_percentage, 1) }}%</td>
                    <td>{{ optional($change->effective_from)->toDateString() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@else
<div class="top">
    <div>
        <div class="page-kicker">Workspace</div>
        <h1>Dashboard</h1>
        <div class="muted">Create and manage customer quotations.</div>
    </div>
    <a class="btn" href="{{ route('quotations.create') }}">＋ New quotation</a>
</div>
<div class="card staff-dashboard-card">
    <h2>Ready to prepare a quote?</h2>
    <p class="muted">Choose a customer, select a product, add the quantity and artwork size, then save the quotation.</p>
    <div class="button-row"><a class="btn" href="{{ route('quotations.create') }}">Create quotation</a><a class="btn btn-secondary" href="{{ route('quotations.index') }}">View quotations</a></div>
</div>
@endif
@endsection
