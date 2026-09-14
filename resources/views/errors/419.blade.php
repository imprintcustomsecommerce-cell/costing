{{--
    Page expired.

    A form sat open longer than the session lasted - two hours by default - and
    the token it carried is no longer the one the server expects. Almost always
    a quotation left open over lunch, so the person arrives here having just
    lost typing, which is the thing to address first.

    Deliberately not phrased as a security failure. The check that fired is a
    security one, but the overwhelming majority of the people who see it simply
    took too long, and telling them their request was forbidden teaches them
    nothing they can act on.

    Written to survive an absent session: this is raised precisely when the
    session is not what was expected, so auth() is asked for nothing here.
--}}
@extends('errors.shell')

@section('error-title', 'Page expired')
@section('error-code', '419')
@section('error-heading', 'This page sat open too long')

@section('error-body')
    <p class="muted">
        For safety the system stops accepting a form once its sign-in has gone stale,
        which happens after {{ (int) config('session.lifetime') >= 60
            ? floor((int) config('session.lifetime') / 60).' hours'
            : (int) config('session.lifetime').' minutes' }} without activity. Nothing was saved.
    </p>

    <p class="muted">
        Sign in again, then use your browser's <b>Back</b> button — most browsers
        put what you had typed back on the form, and you can save it properly from
        there. Copy anything you cannot afford to retype before you do.
    </p>

    <div class="error-actions">
        <a class="btn" href="{{ route('login') }}">Sign in again</a>
    </div>
@endsection
