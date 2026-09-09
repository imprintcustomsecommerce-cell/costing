@extends('layouts.app')
@section('content')
<div class="top">
    <div>
        <div class="page-kicker">Sales</div>
        <h1>New quotation</h1>
        <div class="muted">Pick products and quantities. Prices come from what each product's materials and labour cost.</div>
    </div>
    <a class="btn btn-secondary" href="{{ route('quotations.index') }}">Back to quotations</a>
</div>

@include('quotations._form')
@endsection
