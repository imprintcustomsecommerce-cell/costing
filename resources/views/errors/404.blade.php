{{--
    Not found.

    Two quite different things arrive here. A mistyped address, which needs
    nothing but a way back. And a record that is genuinely gone - a quotation
    somebody deleted, or artwork missing from disk, which aborts 404 with a
    message saying so. When there is such a message it is the more useful
    thing on the page, so it is shown rather than a generic apology.
--}}
@extends('errors.shell')

@section('error-title', 'Not found')
@section('error-code', '404')
@section('error-heading', 'That page is not here')

@section('error-body')
    <p class="muted">
        @if($exception?->getMessage())
            {{ $exception->getMessage() }}
        @else
            The address may have been mistyped, or whatever was here has since been removed.
        @endif
    </p>

    <div class="error-actions">
        @auth
            <a class="btn" href="{{ route('dashboard') }}">Back to dashboard</a>
            <a class="btn btn-secondary" href="{{ route('quotations.index') }}">Quotations</a>
        @else
            <a class="btn" href="{{ route('login') }}">Sign in</a>
        @endauth
    </div>
@endsection
