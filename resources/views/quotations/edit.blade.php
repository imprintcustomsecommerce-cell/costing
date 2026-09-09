@extends('layouts.app')
@section('content')
<div class="top">
    <div>
        <div class="page-kicker">Sales</div>
        <h1>Edit {{ $quotation->number }}</h1>
        <div class="muted">Saving re-prices every line at today's material and labour costs.</div>
    </div>
    <a class="btn btn-secondary" href="{{ route('quotations.show', $quotation) }}">Back to quotation</a>
</div>

@include('quotations._form')
@endsection
