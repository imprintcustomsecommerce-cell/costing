@extends('layouts.app')
@section('content')
<div class="top"><div><div class="page-kicker">Pricing catalog</div><h1>Materials</h1><div class="muted">One active material price: retail or bulk</div></div><a class="btn" href="{{ route('admin.materials.create') }}">＋ Add material</a></div>
<div class="card">
    <form style="max-width:430px"><label>Search materials<input name="q" value="{{ request('q') }}" placeholder="SKU or material name"></label></form>
    <div class="table-wrap" style="margin-top:18px"><table><thead><tr><th>SKU</th><th>Material</th><th>Active price / unit</th><th>Type</th><th>Unit</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse($materials as $m)
        @php($effective=$m->effectiveCostToday()) @php($pending=$m->pendingCostChange())
        <tr><td><strong>{{ $m->sku }}</strong></td><td>{{ $m->name }}</td><td><strong>{{ $effective===null?'—':'₱'.number_format($effective,4) }}</strong>@if($pending)<div class="muted">₱{{ number_format((float)$pending->cost,4) }} from {{ $pending->effective_from->format('M d, Y') }}</div>@endif</td><td>{{ $m->retail_cost === null ? 'Bulk' : 'Retail' }}</td><td>{{ str_replace('_',' ',$m->unit) }}</td><td><span class="status-badge status-{{ $m->is_active?'approved':'draft' }}">{{ $m->is_active?'Active':'Inactive' }}</span></td><td><a class="table-link" href="{{ route('admin.materials.edit',$m) }}">Manage</a></td></tr>
    @empty<tr><td colspan="7" class="empty-state">No materials configured.</td></tr>@endforelse
    </tbody></table></div>{{ $materials->links() }}
</div>
@endsection
