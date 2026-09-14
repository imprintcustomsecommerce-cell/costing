{{--
    Server error.

    This page has a harder job than the others: whatever broke may be the very
    thing a page normally relies on. Sessions and the cache are kept in the
    database, so @auth would open a database connection - and a database that
    is down is the most likely reason for being here at all. A second error
    raised while rendering the first is how a plain fault becomes an
    unreadable one, so nothing here touches the session, the database or the
    signed-in user. Plain links only.

    The exception message is deliberately not shown. It is written to the log,
    where the people who can act on it will look; on the page it would tell a
    customer nothing and an attacker something.
--}}
@extends('errors.shell')

@section('error-title', 'Something went wrong')
@section('error-code', '500')
@section('error-heading', 'Something went wrong at our end')

@section('error-body')
    <p class="muted">
        This is not something you did. The fault has been recorded with the time it
        happened, and nothing you had saved before now has been lost.
    </p>

    <p class="muted">
        Try again in a moment. If it keeps happening, tell whoever looks after the
        system and mention roughly when you saw this — that is enough to find it in
        the log.
    </p>

    <div class="error-actions">
        <a class="btn" href="{{ url('/') }}">Try again</a>
    </div>
@endsection
