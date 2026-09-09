<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102238">
    <title>Sign in · Imprint Customs Costing</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        try {
            var saved = localStorage.getItem('theme');
            if (saved === 'dark' || saved === 'light') {
                document.documentElement.dataset.theme = saved;
            }
        } catch (e) {}
    </script>
</head>
<body>
<main class="login-page">
    <div class="login-site-brand">
        <div class="brand-mark">IC</div>
        <div><strong>Imprint Customs</strong><span>Costing system</span></div>
    </div>
    <div class="login-shell">
        <section class="login-panel">
            <form class="login-form" method="post" action="{{ route('login.store') }}">
                @csrf
                <div class="login-heading">
                    <div class="page-kicker">Staff portal</div>
                    <h2>Sign in</h2>
                    <p>Enter your account details below.</p>
                </div>
                @if($errors->any())<div class="alert-error" role="alert">{{ $errors->first() }}</div>@endif
                <label>Email address
                    <input name="email" type="email" value="{{ old('email') }}" placeholder="name@imprintcustoms.ph" autocomplete="email" required autofocus>
                </label>
                <label>Password
                    <input name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required>
                </label>
                <button class="btn" type="submit">Sign in <span aria-hidden="true">→</span></button>
                <p class="login-footnote"><span aria-hidden="true">●</span> Secure access for authorized staff</p>
            </form>
        </section>
    </div>
</main>
</body>
</html>
