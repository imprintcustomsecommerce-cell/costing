{{--
    Forbidden.

    Reached when a signed-in account opens something its role does not cover:
    the admin screens are limited to ADMIN and SUPER ADMIN, and settings and
    accounts to SUPER ADMIN alone. It is not a mistake to be apologised for -
    the person is signed in correctly, they simply do not do this job - so the
    page says which role would be needed and offers the way back rather than
    leaving them at a dead end.

    Standalone rather than extending layouts.app: that layout reads
    auth()->user()->name unguarded, which would fatal inside an error page on
    the one occasion the session is already gone.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102238">
    <title>Not your access &middot; Imprint Customs Costing</title>
    @vite(['resources/css/app.css'])
    <script>
        try {
            var saved = localStorage.getItem('theme');
            if (saved === 'dark' || saved === 'light') {
                document.documentElement.dataset.theme = saved;
            }
        } catch (e) { /* private mode: fall back to the OS preference */ }
    </script>
</head>
<body class="app-body">

<main class="error-page">
    <div class="error-card">
        <div class="error-code">403</div>
        <h1>That screen is not for your account</h1>

        <p class="muted">
            @if($exception?->getMessage())
                {{ $exception->getMessage() }}
            @else
                You are signed in, but this part of the system is limited to another role.
            @endif
        </p>

        @auth
            <dl class="error-meta">
                <div>
                    <dt>Signed in as</dt>
                    <dd>{{ auth()->user()->name }}</dd>
                </div>
                <div>
                    <dt>Your role</dt>
                    <dd>{{ auth()->user()->getRoleNames()->first() ?: 'No role assigned' }}</dd>
                </div>
            </dl>

            <p class="muted">
                Materials, products and audit logs need <b>ADMIN</b>. Settings and user
                accounts need <b>SUPER ADMIN</b>. If you should have that access, ask a
                Super Admin to change your role on the Users screen.
            </p>
        @endauth

        <div class="error-actions">
            @auth
                <a class="btn" href="{{ route('dashboard') }}">Back to dashboard</a>
                <a class="btn btn-secondary" href="{{ route('quotations.index') }}">Quotations</a>
            @else
                <a class="btn" href="{{ route('login') }}">Sign in</a>
            @endauth
        </div>
    </div>
</main>

</body>
</html>
