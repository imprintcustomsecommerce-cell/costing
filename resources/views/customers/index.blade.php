@extends('layouts.app')
@section('content')
<div class="top">
    <div>
        <div class="page-kicker">Sales</div>
        <h1>Customers</h1>
        <div class="muted">Who you quote, and what they have been quoted.</div>
    </div>
    <a class="btn" href="{{ route('customers.create') }}">＋ Add customer</a>
</div>

<div class="card">
    <form style="max-width:430px">
        <label>Search customers<input name="q" value="{{ request('q') }}" placeholder="Name, company or code"></label>
    </form>

    <div class="table-wrap" style="margin-top:18px">
        <table class="config-table">
            <thead><tr><th>Customer</th><th>Contact</th><th class="num">Quotations</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td><span class="name">{{ $customer->label() }}</span><span class="sub">{{ $customer->code }}@if($customer->company_name) · {{ $customer->contact_name }}@endif</span></td>
                    <td>{{ $customer->phone ?: '—' }}@if($customer->email)<span class="sub">{{ $customer->email }}</span>@endif</td>
                    <td class="num">{{ $customer->quotations_count }}</td>
                    <td><span class="status-badge {{ $customer->is_active ? 'is-on' : 'is-off' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td><a class="table-link" href="{{ route('customers.edit',$customer) }}">Manage</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-state">No customers yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $customers->links() }}
</div>
@endsection
