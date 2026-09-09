@extends('layouts.app')
@section('content')
<div class="top">
    <div>
        <div class="page-kicker">Sales</div>
        <h1>{{ $customer->exists ? $customer->label() : 'New customer' }}</h1>
        <div class="muted">{{ $customer->exists ? $customer->code : 'A reference is generated when you save.' }}</div>
    </div>
    <a class="btn btn-secondary" href="{{ route('customers.index') }}">Back to customers</a>
</div>

<form class="card" method="post" action="{{ $customer->exists ? route('customers.update',$customer) : route('customers.store') }}">
    @csrf
    @if($customer->exists) @method('put') @endif
    @if($errors->any())<div class="alert-error">{{ $errors->first() }}</div>@endif

    <div class="form-grid form-grid-three">
        <label>Contact person<input name="contact_name" value="{{ old('contact_name',$customer->contact_name) }}" required placeholder="e.g. Juan Dela Cruz"></label>
        <label>Company <span class="cost-helper-optional">optional</span><input name="company_name" value="{{ old('company_name',$customer->company_name) }}" placeholder="e.g. Falcon Riders MC"></label>
        <label>Phone<input name="phone" value="{{ old('phone',$customer->phone) }}" placeholder="e.g. 0917-555-1234"></label>
        <label>Email<input type="email" name="email" value="{{ old('email',$customer->email) }}"></label>
        <label style="grid-column:span 2">Address<input name="address" value="{{ old('address',$customer->address) }}"></label>
    </div>

    <label class="field-block">Notes<textarea name="notes">{{ old('notes',$customer->notes) }}</textarea></label>
    <label class="choice-card" style="margin-bottom:16px">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->exists ? $customer->is_active : true))>
        <span>Active — offered when creating a quotation</span>
    </label>

    <div class="form-actions">
        <button class="btn">Save customer</button>
        <a class="btn btn-secondary" href="{{ route('customers.index') }}">Cancel</a>
    </div>
</form>

@if($customer->exists && $customer->quotations->isNotEmpty())
<section class="card" style="margin-top:20px">
    <div class="section-heading" style="margin-top:0"><div><h2>Recent quotations</h2><p class="muted">The last ten for this customer.</p></div></div>
    <div class="table-wrap">
        <table class="config-table">
            <thead><tr><th>Number</th><th class="num">Total</th><th>Created</th><th></th></tr></thead>
            <tbody>
            @foreach($customer->quotations as $quotation)
                <tr>
                    <td><span class="name">{{ $quotation->number }}</span></td>
                    <td class="num">₱{{ number_format((float) $quotation->total, 2) }}</td>
                    <td>{{ $quotation->created_at->toDateString() }}</td>
                    <td><a class="table-link" href="{{ route('quotations.show',$quotation) }}">Open</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif
@endsection
