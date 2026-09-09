@extends('layouts.app')
@section('content')
<div class="top">
    <div>
        <div class="page-kicker">Product catalog</div>
        <h1>Products</h1>
        <div class="muted">What each product is made of, and what those materials cost.</div>
    </div>
    <a class="btn" href="{{ route('admin.products.create') }}">＋ Add product</a>
</div>

<div class="card">
    <form style="max-width:430px">
        <label>Search products<input name="q" value="{{ request('q') }}" placeholder="SKU or product name"></label>
    </form>

    <div class="table-wrap" style="margin-top:18px">
        <table class="config-table">
            <thead><tr>
                <th>Product</th><th>Category</th><th class="num">Materials</th>
                <th class="num">Material cost</th><th>Status</th><th></th>
            </tr></thead>
            <tbody>
            @forelse($products as $product)
                @php($costing = $product->costing())
                <tr>
                    <td><span class="name">{{ $product->name }}</span><span class="sub">{{ $product->sku }}</span></td>
                    <td>{{ $product->category?->name ?: '—' }}</td>
                    <td class="num">
                        {{ $product->materials->count() }}
                        @if($product->materials->isEmpty())<span class="sub">costs nothing</span>@endif
                    </td>
                    <td class="num"><span class="name">₱{{ number_format($costing['retail'], 2) }}</span></td>
                    <td><span class="status-badge {{ $product->is_active ? 'is-on' : 'is-off' }}">{{ $product->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td><a class="table-link" href="{{ route('admin.products.edit',$product) }}">Manage</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-state">No products yet. Add one to see what it costs.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $products->links() }}
</div>
@endsection
